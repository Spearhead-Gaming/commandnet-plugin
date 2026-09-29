<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Tests\Unit\Scheduler;

use MajesticDev\CommandNet\Scheduler\ReportInTaskHandler;
use MajesticDev\CommandNet\Service\ReportInService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;

class ReportInTaskHandlerTest extends TestCase
{
    public function testTheCronTaskRunsTheReportInChecksAndSucceeds(): void
    {
        $service = $this->createMock(ReportInService::class);
        $service->expects($this->once())->method('runChecks');

        $this->assertSame(Command::SUCCESS, (new ReportInTaskHandler($service))());
    }
}
