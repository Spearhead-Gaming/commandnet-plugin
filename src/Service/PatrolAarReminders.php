<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Service;

use DateTimeImmutable;
use Forumify\Core\Entity\Notification;
use Forumify\Core\Notification\GenericNotificationType;
use Forumify\Core\Notification\NotificationService;
use MajesticDev\CommandNet\Entity\Enum\AarStatus;
use MajesticDev\CommandNet\Entity\Operation;
use MajesticDev\CommandNet\Repository\OperationRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Tells a patrol's leader, with a forum notification, when their AAR falls due (the patrol has
 * ended) and again when it is overdue. Nothing is stored: EventRules::reminderFor() only says
 * yes within one run interval of each moment, so a run every hour sends each reminder once.
 *
 * ponytail: a missed scheduler run skips that reminder; add a "reminded" flag on the operation
 * if that ever matters.
 */
class PatrolAarReminders
{
    /** Must match the schedule in PatrolAarReminderTaskHandler. */
    public const int INTERVAL_SECONDS = 3600;

    public function __construct(
        private readonly OperationRepository $operationRepository,
        private readonly EventRules $eventRules,
        private readonly NotificationService $notificationService,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly PatrolReminderNotifier $reminderNotifier,
    ) {
    }

    /**
     * @return int how many reminders were sent
     */
    public function run(?DateTimeImmutable $now = null): int
    {
        $now ??= new DateTimeImmutable();

        $sent = 0;
        foreach ($this->operationRepository->findPatrolsAwaitingAar(null, $now) as $patrol) {
            $leader = $patrol->getLeader();
            $status = $this->eventRules->reminderFor($patrol, $now, self::INTERVAL_SECONDS);
            if ($leader === null || $status === null) {
                continue;
            }

            $this->notificationService->sendNotification(new Notification(
                GenericNotificationType::TYPE,
                $leader,
                $this->payload($patrol, $status),
            ));
            $this->reminderNotifier->notify($patrol, $status);
            ++$sent;
        }

        return $sent;
    }

    /**
     * @return array{title: string, description: string, url: string}
     */
    private function payload(Operation $patrol, AarStatus $status): array
    {
        $deadline = $this->eventRules->aarDueAt($patrol)->format('Y-m-d H:i');

        return [
            'title' => $status === AarStatus::OVERDUE ? 'AAR overdue' : 'AAR due',
            'description' => $status === AarStatus::OVERDUE
                ? sprintf('The after-action report for your patrol "%s" was due %s and has not been filed.', $patrol->getTitle(), $deadline)
                : sprintf('Your patrol "%s" has ended. Please file its after-action report by %s.', $patrol->getTitle(), $deadline),
            'url' => $this->urlGenerator->generate('command_net_operation_aar', ['id' => $patrol->getId()]),
        ];
    }
}
