<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Service;

use DateTime;
use DomainException;
use Forumify\Core\Entity\Notification;
use Forumify\Core\Entity\User;
use Forumify\Core\Notification\GenericNotificationType;
use Forumify\Core\Notification\NotificationService;
use MajesticDev\CommandNet\Entity\Assignment;
use MajesticDev\CommandNet\Entity\EnlistmentApplication;
use MajesticDev\CommandNet\Entity\Enum\ApplicationStatus;
use MajesticDev\CommandNet\Entity\Enum\ServiceRecordType;
use MajesticDev\CommandNet\Entity\Enum\SoldierStatus;
use MajesticDev\CommandNet\Entity\ServiceRecord;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Repository\AssignmentRepository;
use MajesticDev\CommandNet\Repository\EnlistmentApplicationRepository;
use MajesticDev\CommandNet\Repository\RankRepository;
use MajesticDev\CommandNet\Repository\ServiceRecordRepository;
use MajesticDev\CommandNet\Repository\SoldierProfileRepository;
use MajesticDev\CommandNet\Repository\UnitRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Applying and reviewing. Accepting an application is what creates the personnel file (or
 * restores a discharged or retired one) with the configured starting rank and unit, so a
 * new soldier arrives already on the roster instead of needing a manual set-up pass.
 */
class EnlistmentService
{
    public function __construct(
        private readonly EnlistmentSettings $settings,
        private readonly EnlistmentApplicationRepository $applicationRepository,
        private readonly SoldierProfileRepository $soldierProfileRepository,
        private readonly ServiceRecordRepository $serviceRecordRepository,
        private readonly AssignmentRepository $assignmentRepository,
        private readonly RankRepository $rankRepository,
        private readonly UnitRepository $unitRepository,
        private readonly UnitRoleSyncer $unitRoleSyncer,
        private readonly RankRoleSyncer $rankRoleSyncer,
        private readonly NotificationService $notificationService,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * Why this user can't apply right now, or null if they can.
     */
    public function ineligibleReason(User $user): ?string
    {
        if (!$this->settings->all()['enabled']) {
            return 'Enlistment is not open right now.';
        }
        if ($user->isBanned()) {
            return 'You are not able to apply.';
        }
        if (!$user->isEmailVerified()) {
            return 'Verify your email address before applying.';
        }

        $profile = $this->soldierProfileRepository->findOneBy(['user' => $user]);
        if ($profile !== null && $profile->isEnlisted()) {
            return 'You are already enlisted.';
        }

        if ($this->applicationRepository->findLatestFor($user)?->isPending()) {
            return 'You already have an application waiting for review.';
        }

        return null;
    }

    public function submit(EnlistmentApplication $application): void
    {
        $reason = $this->ineligibleReason($application->getUser());
        if ($reason !== null) {
            throw new DomainException($reason);
        }

        $this->applicationRepository->save($application);
    }

    public function accept(EnlistmentApplication $application, ?User $reviewer, ?string $note): SoldierProfile
    {
        $this->assertPending($application);

        $user = $application->getUser();
        $profile = $this->soldierProfileRepository->findOneBy(['user' => $user]) ?? new SoldierProfile($user);

        $profile->setStatus(SoldierStatus::ACTIVE);
        $profile->setEnlistmentDate(new DateTime());
        $profile->setDischargeDate(null);
        if ($profile->getCallsign() === null) {
            $profile->setCallsign($application->getCallsign());
        }
        if ($profile->getSteamId() === null) {
            $profile->setSteamId($application->getSteamId());
        }
        $rankId = $this->settings->all()['defaultRank'];
        if ($profile->getRank() === null && $rankId !== null) {
            $profile->setRank($this->rankRepository->find($rankId));
        }
        $this->soldierProfileRepository->save($profile);

        $this->serviceRecordRepository->save(new ServiceRecord($profile, ServiceRecordType::ENLISTMENT, 'Enlisted'));
        $this->assignToDefaultUnit($profile);
        $this->rankRoleSyncer->sync($profile);

        $application->decide(ApplicationStatus::ACCEPTED, $reviewer, $note);
        $this->applicationRepository->save($application);
        $this->notify(
            $user,
            'Application accepted',
            'Your application was accepted - welcome aboard.' . ($note ? " $note" : ''),
            $this->urlGenerator->generate('command_net_roster_profile', ['username' => $user->getUsername()]),
        );

        return $profile;
    }

    public function decline(EnlistmentApplication $application, ?User $reviewer, ?string $note): void
    {
        $this->assertPending($application);

        $application->decide(ApplicationStatus::DECLINED, $reviewer, $note);
        $this->applicationRepository->save($application);
        $this->notify(
            $application->getUser(),
            'Application declined',
            'Your application was not accepted.' . ($note ? " $note" : ''),
            $this->urlGenerator->generate('command_net_enlist'),
        );
    }

    /**
     * A returning soldier who still has an open primary posting keeps it.
     */
    private function assignToDefaultUnit(SoldierProfile $profile): void
    {
        $unitId = $this->settings->all()['defaultUnit'];
        $unit = $unitId !== null ? $this->unitRepository->find($unitId) : null;
        if ($unit === null || $profile->getPrimaryAssignment() !== null) {
            return;
        }

        $assignment = new Assignment($profile, $unit);
        // The inverse collection isn't refreshed by saving, and the role sync below reads it.
        $profile->addAssignment($assignment);
        $this->assignmentRepository->save($assignment);

        $record = new ServiceRecord($profile, ServiceRecordType::ASSIGNMENT, $unit->getName());
        $record->setSource(ServiceRecord::SOURCE_ASSIGNMENT, $assignment->getId());
        $this->serviceRecordRepository->save($record);

        $this->unitRoleSyncer->sync($profile);
    }

    private function assertPending(EnlistmentApplication $application): void
    {
        if (!$application->isPending()) {
            throw new DomainException('This application has already been reviewed.');
        }
    }

    private function notify(User $user, string $title, string $text, string $url): void
    {
        $this->notificationService->sendNotification(new Notification(
            GenericNotificationType::TYPE,
            $user,
            ['title' => $title, 'description' => $text, 'url' => $url],
        ));
    }
}
