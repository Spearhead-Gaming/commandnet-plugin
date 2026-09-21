<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Tests\Unit\Service;

use DateTime;
use DomainException;
use Forumify\Core\Entity\User;
use MajesticDev\CommandNet\Entity\Assignment;
use MajesticDev\CommandNet\Entity\Enum\DischargeKind;
use MajesticDev\CommandNet\Entity\Enum\ServiceRecordType;
use MajesticDev\CommandNet\Entity\Enum\SoldierStatus;
use MajesticDev\CommandNet\Entity\ServiceRecord;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Entity\Unit;
use MajesticDev\CommandNet\Repository\AssignmentRepository;
use MajesticDev\CommandNet\Repository\ServiceRecordRepository;
use MajesticDev\CommandNet\Repository\SoldierProfileRepository;
use MajesticDev\CommandNet\Service\AwolService;
use MajesticDev\CommandNet\Service\DischargeService;
use MajesticDev\CommandNet\Service\RankRoleSyncer;
use MajesticDev\CommandNet\Service\SpecialtyRoleSyncer;
use MajesticDev\CommandNet\Service\UnitRoleSyncer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DischargeServiceTest extends TestCase
{
    private DischargeService $service;
    private SoldierProfileRepository&MockObject $soldierRepository;
    private UnitRoleSyncer&MockObject $unitRoleSyncer;
    private RankRoleSyncer&MockObject $rankRoleSyncer;
    private SpecialtyRoleSyncer&MockObject $specialtyRoleSyncer;
    private AwolService&MockObject $awolService;

    /** @var ServiceRecord[] */
    private array $records = [];

    protected function setUp(): void
    {
        $this->soldierRepository = $this->createMock(SoldierProfileRepository::class);
        $recordRepository = $this->createMock(ServiceRecordRepository::class);
        $recordRepository->method('save')->willReturnCallback(function (ServiceRecord $record): void {
            $this->records[] = $record;
        });
        $this->unitRoleSyncer = $this->createMock(UnitRoleSyncer::class);
        $this->rankRoleSyncer = $this->createMock(RankRoleSyncer::class);
        $this->specialtyRoleSyncer = $this->createMock(SpecialtyRoleSyncer::class);
        $this->awolService = $this->createMock(AwolService::class);

        $this->service = new DischargeService(
            $this->soldierRepository,
            $recordRepository,
            $this->createMock(AssignmentRepository::class),
            $this->unitRoleSyncer,
            $this->rankRoleSyncer,
            $this->specialtyRoleSyncer,
            $this->awolService,
        );
    }

    public function testDischargeEndsOpenAssignmentsRecordsItAndRevokesRoles(): void
    {
        $soldier = new SoldierProfile(new User());
        $primary = $this->assignment($soldier);
        $secondary = $this->assignment($soldier, primary: false);
        $alreadyEnded = $this->assignment($soldier, ended: new DateTime('2020-01-01'));
        $date = new DateTime('2026-06-01');

        $this->soldierRepository->expects($this->once())->method('save')->with($soldier);
        $this->unitRoleSyncer->expects($this->once())->method('sync')->with($soldier);
        $this->rankRoleSyncer->expects($this->once())->method('sync')->with($soldier, true);
        $this->specialtyRoleSyncer->expects($this->once())->method('sync')->with($soldier, true);
        $this->awolService->expects($this->once())->method('revokeRole')->with($soldier);

        $this->service->discharge($soldier, DischargeKind::HONORABLE, 'Moved on.', $date);

        $this->assertSame(SoldierStatus::DISCHARGED, $soldier->getStatus());
        $this->assertSame($date, $soldier->getDischargeDate());
        $this->assertSame($date, $primary->getEndDate());
        $this->assertSame($date, $secondary->getEndDate());
        $this->assertEquals(new DateTime('2020-01-01'), $alreadyEnded->getEndDate());
        $this->assertNull($soldier->getPrimaryAssignment());

        $this->assertCount(1, $this->records);
        $this->assertSame(ServiceRecordType::DISCHARGE, $this->records[0]->getType());
        $this->assertSame('Honorable Discharge', $this->records[0]->getTitle());
        $this->assertSame('Moved on.', $this->records[0]->getDescription());
        $this->assertSame($date, $this->records[0]->getDate());
    }

    public function testRetirementSetsTheRetiredStatus(): void
    {
        $soldier = new SoldierProfile(new User());

        $this->service->discharge($soldier, DischargeKind::RETIREMENT, null, new DateTime());

        $this->assertSame(SoldierStatus::RETIRED, $soldier->getStatus());
        $this->assertNull($this->records[0]->getDescription());
        $this->assertFalse($soldier->isEnlisted());
    }

    public function testAnAlreadyDischargedSoldierCannotBeDischargedAgain(): void
    {
        $soldier = new SoldierProfile(new User());
        $soldier->setStatus(SoldierStatus::DISCHARGED);

        $this->soldierRepository->expects($this->never())->method('save');
        $this->awolService->expects($this->never())->method('revokeRole');

        $this->expectException(DomainException::class);
        $this->service->discharge($soldier, DischargeKind::GENERAL, null, new DateTime());
    }

    public function testEveryKindMapsToTheRightStatus(): void
    {
        $this->assertSame(SoldierStatus::RETIRED, DischargeKind::RETIREMENT->status());
        foreach ([DischargeKind::GENERAL, DischargeKind::HONORABLE, DischargeKind::DISHONORABLE] as $kind) {
            $this->assertSame(SoldierStatus::DISCHARGED, $kind->status());
        }
    }

    private function assignment(SoldierProfile $soldier, bool $primary = true, ?DateTime $ended = null): Assignment
    {
        $assignment = new Assignment($soldier, new Unit());
        $assignment->setIsPrimary($primary);
        $assignment->setEndDate($ended);
        $soldier->addAssignment($assignment);

        return $assignment;
    }
}
