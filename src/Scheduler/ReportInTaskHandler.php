<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Scheduler;

use MajesticDev\CommandNet\Service\ReportInService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

#[AsCommand('command-net:report-in:run-checks')]
#[AsCronTask('0 8 * * *', jitter: 1800)]
class ReportInTaskHandler
{
    public function __construct(private readonly ReportInService $reportInService)
    {
    }

    public function __invoke(): int
    {
        $this->reportInService->runChecks();

        return Command::SUCCESS;
    }
}
