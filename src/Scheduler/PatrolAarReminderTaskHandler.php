<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Scheduler;

use MajesticDev\CommandNet\Service\PatrolAarReminders;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

/**
 * Hourly, with no jitter: PatrolAarReminders relies on one run per hour to send each reminder once.
 */
#[AsCommand('command-net:patrols:send-aar-reminders')]
#[AsCronTask('0 * * * *')]
class PatrolAarReminderTaskHandler
{
    public function __construct(private readonly PatrolAarReminders $reminders)
    {
    }

    public function __invoke(): int
    {
        $this->reminders->run();

        return Command::SUCCESS;
    }
}
