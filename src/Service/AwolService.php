<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Service;

use Forumify\Core\Entity\Notification;
use Forumify\Core\Notification\GenericNotificationType;
use Forumify\Core\Notification\NotificationService;
use Forumify\Core\Repository\RoleRepository;
use Forumify\Core\Repository\UserRepository;
use MajesticDev\CommandNet\Entity\Enum\ServiceRecordType;
use MajesticDev\CommandNet\Entity\Enum\SoldierStatus;
use MajesticDev\CommandNet\Entity\OperationRSVP;
use MajesticDev\CommandNet\Entity\ServiceRecord;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Repository\OperationRSVPRepository;
use MajesticDev\CommandNet\Repository\ServiceRecordRepository;
use MajesticDev\CommandNet\Repository\SoldierProfileRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Flags/clears AWOL based on attendance rather than a scheduled report-in check: a
 * configurable number of consecutive missed operations flags a soldier AWOL, and their
 * next attended operation clears it. The streak is scoped to the soldier's current unit
 * (an operation with no unit set still counts) so transferring units doesn't carry over
 * absences racked up somewhere else - mirrors MILHQ's per-unit consecutive-absence window.
 * Discord visibility is the same zero-Discord-code trick as UnitRoleSyncer - grant/revoke a
 * plain forumify Role and let the Discord plugin's own Role<->Discord-Role mapping do the
 * rest. Every flip also writes a ServiceRecord and notifies the soldier, matching MILHQ's
 * own audit-trail + notification behavior for consecutive absences.
 */
class AwolService
{
    public function __construct(
        private readonly AwolSettings $settings,
        private readonly AttendanceCalculator $attendanceCalculator,
        private readonly OperationRSVPRepository $rsvpRepository,
        private readonly RoleRepository $roleRepository,
        private readonly SoldierProfileRepository $soldierProfileRepository,
        private readonly ServiceRecordRepository $serviceRecordRepository,
        private readonly UserRepository $userRepository,
        private readonly NotificationService $notificationService,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly EventRules $eventRules,
    ) {
    }

    public function checkAfterAttendanceChange(SoldierProfile $profile): void
    {
        $settings = $this->settings->all();
        if (!$settings['enabled']) {
            return;
        }

        $unit = $profile->getPrimaryAssignment()?->getUnit();
        // Only Operations count toward the streak; a no-show at a patrol or training is ignored.
        $history = array_values(array_filter(
            $this->rsvpRepository->findAttendanceHistoryForUnit($profile, $unit, $profile->getActiveSince()),
            fn (OperationRSVP $rsvp): bool => $this->eventRules->countsTowardAwol($rsvp->getOperation()->getType()),
        ));
        $missStreak = $this->attendanceCalculator->missStreakFrom($history);

        if ($missStreak >= (int)$settings['missThreshold'] && $profile->getStatus() === SoldierStatus::ACTIVE) {
            $this->flagAwol(
                $profile,
                "Flagged AWOL after $missStreak consecutive missed operations.",
                "You've missed $missStreak operations in a row and have been marked AWOL. Please contact your leadership.",
            );
            return;
        }

        // Only undo an AWOL this service set - an admin-set one stays until an admin clears it.
        if ($missStreak === 0 && $profile->getStatus() === SoldierStatus::AWOL && $profile->isAwolAutoFlagged()) {
            $this->clearAwol(
                $profile,
                'Returned to Active status after attending an operation.',
                'Welcome back - your status has been reverted to Active.',
            );
        }
    }

    /**
     * Flags a soldier AWOL, grants the AWOL role, and writes the audit record and notification.
     * $byReportIn marks the flag as coming from a missed report in rather than from attendance,
     * which decides what is allowed to clear it again.
     */
    public function flagAwol(SoldierProfile $profile, string $recordText, string $notificationText, bool $byReportIn = false): void
    {
        $profile->setStatus(SoldierStatus::AWOL);
        if ($byReportIn) {
            $profile->setReportInFlagged(true);
        } else {
            $profile->setAwolAutoFlagged(true);
        }
        $this->soldierProfileRepository->save($profile);
        $this->syncRole($profile, $this->settings->all()['role'], grant: true);
        $this->logAndNotify($profile, 'Flagged AWOL', $recordText, $notificationText);
    }

    public function clearAwol(SoldierProfile $profile, string $recordText, string $notificationText): void
    {
        $profile->setStatus(SoldierStatus::ACTIVE);
        $this->soldierProfileRepository->save($profile);
        $this->syncRole($profile, $this->settings->all()['role'], grant: false);
        $this->logAndNotify($profile, 'Returned to Active', $recordText, $notificationText);
    }

    /**
     * Takes the AWOL role off without touching status or writing a record - for a soldier who is
     * leaving, where the discharge itself is what gets recorded.
     */
    public function revokeRole(SoldierProfile $profile): void
    {
        $this->syncRole($profile, $this->settings->all()['role'], grant: false);
    }

    public function notify(SoldierProfile $profile, string $title, string $text): void
    {
        $this->notificationService->sendNotification(new Notification(
            GenericNotificationType::TYPE,
            $profile->getUser(),
            [
                'title' => $title,
                'description' => $text,
                'url' => $this->urlGenerator->generate('command_net_roster_profile', [
                    'username' => $profile->getUser()->getUsername(),
                ]),
            ],
        ));
    }

    private function syncRole(SoldierProfile $profile, ?int $roleId, bool $grant): void
    {
        if ($roleId === null) {
            return;
        }

        $role = $this->roleRepository->find($roleId);
        if ($role === null) {
            return;
        }

        $user = $profile->getUser();
        $roles = $user->getRoleEntities();
        if ($grant) {
            $changed = !$roles->contains($role);
            if ($changed) {
                $user->addRoleEntity($role);
            }
        } else {
            $changed = $roles->removeElement($role);
        }

        if ($changed) {
            $this->userRepository->save($user);
        }
    }

    private function logAndNotify(SoldierProfile $profile, string $title, string $recordText, string $notificationText): void
    {
        $record = new ServiceRecord($profile, ServiceRecordType::AWOL, $title);
        $record->setDescription($recordText);
        $this->serviceRecordRepository->save($record);

        $this->notify($profile, $title, $notificationText);
    }
}
