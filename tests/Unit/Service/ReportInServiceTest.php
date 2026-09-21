<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Tests\Unit\Service;

use DateTime;
use DateTimeImmutable;
use Forumify\Core\Entity\User;
use MajesticDev\CommandNet\Entity\Enum\SoldierStatus;
use MajesticDev\CommandNet\Entity\ReportIn;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Repository\ReportInRepository;
use MajesticDev\CommandNet\Repository\SoldierProfileRepository;
use MajesticDev\CommandNet\Service\AwolService;
use MajesticDev\CommandNet\Service\ReportInService;
use MajesticDev\CommandNet\Service\ReportInSettings;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ReportInServiceTest extends TestCase
{
    private const NOW = '2026-06-30 08:00:00';

    private ReportInSettings&MockObject $settings;
    private SoldierProfileRepository&MockObject $soldierRepository;
    private ReportInRepository&MockObject $reportInRepository;
    private AwolService&MockObject $awolService;
    private ReportInService $service;

    protected function setUp(): void
    {
        $this->settings = $this->createMock(ReportInSettings::class);
        $this->soldierRepository = $this->createMock(SoldierProfileRepository::class);
        $this->reportInRepository = $this->createMock(ReportInRepository::class);
        $this->awolService = $this->createMock(AwolService::class);

        $this->service = new ReportInService(
            $this->settings,
            $this->soldierRepository,
            $this->reportInRepository,
            $this->awolService,
        );
    }

    public function testDoesNothingWhenDisabled(): void
    {
        $this->configure(enabled: false);
        $this->soldierRepository->expects($this->never())->method('findBy');

        $this->service->runChecks(new DateTimeImmutable(self::NOW));
    }

    public function testSoldierWithNoReportInGetsABaselineInsteadOfBeingFlagged(): void
    {
        $this->configure();
        $soldier = $this->soldier(null);
        $this->soldierRepository->method('findBy')->willReturn([$soldier]);

        $this->reportInRepository->expects($this->once())->method('save')->with($this->isInstanceOf(ReportIn::class));
        $this->awolService->expects($this->never())->method('flagAwol');

        $this->service->runChecks(new DateTimeImmutable(self::NOW));

        $this->assertNotNull($soldier->getLastReportIn());
    }

    public function testFlagsSoldierPastThePeriod(): void
    {
        $this->configure(period: 30);
        $soldier = $this->soldier(31);
        $this->soldierRepository->method('findBy')->willReturn([$soldier]);

        $this->awolService->expects($this->once())->method('flagAwol')
            ->with($soldier, $this->anything(), $this->anything(), true);

        $this->service->runChecks(new DateTimeImmutable(self::NOW));
    }

    public function testSoldierExactlyAtThePeriodIsNotFlaggedYetButIsWarned(): void
    {
        $this->configure(period: 30, warning: 7);
        $soldier = $this->soldier(30);
        $this->soldierRepository->method('findBy')->willReturn([$soldier]);

        $this->awolService->expects($this->never())->method('flagAwol');
        $this->awolService->expects($this->once())->method('notify')
            ->with($soldier, $this->anything(), $this->stringContains('1 day(s)'));

        $this->service->runChecks(new DateTimeImmutable(self::NOW));
    }

    public function testNoWarningOutsideTheWarningWindow(): void
    {
        $this->configure(period: 30, warning: 7);
        $this->soldierRepository->method('findBy')->willReturn([$this->soldier(5)]);

        $this->awolService->expects($this->never())->method('flagAwol');
        $this->awolService->expects($this->never())->method('notify');

        $this->service->runChecks(new DateTimeImmutable(self::NOW));
    }

    public function testNoWarningsWhenWarningDaysIsZero(): void
    {
        $this->configure(period: 30, warning: 0);
        $this->soldierRepository->method('findBy')->willReturn([$this->soldier(29)]);

        $this->awolService->expects($this->never())->method('notify');

        $this->service->runChecks(new DateTimeImmutable(self::NOW));
    }

    public function testReportingInRestoresASoldierFlaggedForMissingIt(): void
    {
        $soldier = $this->soldier(40);
        $soldier->setStatus(SoldierStatus::AWOL);
        $soldier->setReportInFlagged(true);

        $this->awolService->expects($this->once())->method('clearAwol')->with($soldier);

        $this->service->handleReportedIn($soldier);
    }

    public function testReportingInLeavesAnAttendanceOrAdminAwolAlone(): void
    {
        $soldier = $this->soldier(40);
        $soldier->setStatus(SoldierStatus::AWOL);
        $soldier->setAwolAutoFlagged(true);

        $this->awolService->expects($this->never())->method('clearAwol');

        $this->service->handleReportedIn($soldier);
    }

    private function configure(bool $enabled = true, int $period = 30, int $warning = 7): void
    {
        $this->settings->method('all')->willReturn([
            'enabled' => $enabled,
            'periodDays' => $period,
            'warningDays' => $warning,
        ]);
    }

    private function soldier(?int $daysAgo): SoldierProfile
    {
        $soldier = new SoldierProfile($this->createStub(User::class));
        if ($daysAgo !== null) {
            $soldier->setLastReportIn(new DateTime(self::NOW . " -$daysAgo days"));
        }

        return $soldier;
    }
}
