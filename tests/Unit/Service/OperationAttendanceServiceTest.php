<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Tests\Unit\Service;

use DateTime;
use Forumify\Core\Entity\User;
use MajesticDev\CommandNet\Entity\Assignment;
use MajesticDev\CommandNet\Entity\Enum\ServiceRecordType;
use MajesticDev\CommandNet\Entity\Operation;
use MajesticDev\CommandNet\Entity\OperationAAR;
use MajesticDev\CommandNet\Entity\OperationRSVP;
use MajesticDev\CommandNet\Entity\ServiceRecord;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Entity\Unit;
use MajesticDev\CommandNet\Repository\OperationRSVPRepository;
use MajesticDev\CommandNet\Repository\ServiceRecordRepository;
use MajesticDev\CommandNet\Repository\SoldierProfileRepository;
use MajesticDev\CommandNet\Service\AwolService;
use MajesticDev\CommandNet\Service\OperationAttendanceService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class OperationAttendanceServiceTest extends TestCase
{
    private OperationRSVPRepository&MockObject $rsvpRepository;
    private SoldierProfileRepository&MockObject $soldierRepository;
    private AwolService&MockObject $awolService;
    private OperationAttendanceService $service;

    /** @var ServiceRecord[] records passed to save() */
    private array $saved = [];
    /** @var ServiceRecord[] records passed to remove() */
    private array $removed = [];
    /** @var array<string, ServiceRecord[]> pre-existing records keyed "type:id" */
    private array $existing = [];

    protected function setUp(): void
    {
        $this->rsvpRepository = $this->createMock(OperationRSVPRepository::class);
        $this->soldierRepository = $this->createMock(SoldierProfileRepository::class);
        $recordRepository = $this->createMock(ServiceRecordRepository::class);
        $this->awolService = $this->createMock(AwolService::class);

        $recordRepository->method('findBySource')
            ->willReturnCallback(fn (string $type, int $id) => $this->existing["$type:$id"] ?? []);
        $recordRepository->method('save')
            ->willReturnCallback(function (ServiceRecord $record): void {
                $this->saved[] = $record;
            });
        $recordRepository->method('remove')
            ->willReturnCallback(function (ServiceRecord $record): void {
                $this->removed[] = $record;
            });

        $this->service = new OperationAttendanceService(
            $this->rsvpRepository,
            $this->soldierRepository,
            $recordRepository,
            $this->awolService,
        );
    }

    public function testMarkCreatesRsvpForSoldierWhoNeverResponded(): void
    {
        $operation = $this->operation(1);
        $soldier = $this->soldier(10);

        $this->rsvpRepository->expects($this->once())->method('save')
            ->with($this->callback(
                static fn (OperationRSVP $r) => $r->getSoldier() === $soldier && $r->getAttended() === false,
            ));
        $this->awolService->expects($this->once())->method('checkAfterAttendanceChange')->with($soldier);

        $this->service->mark($operation, $soldier, false);

        $this->assertNotNull($operation->getRsvpFor($soldier));
    }

    public function testNoCombatRecordsUntilAnAarExists(): void
    {
        $this->service->mark($this->operation(1), $this->soldier(10), true);

        $this->assertSame([], $this->combatRecords());
    }

    public function testManyAarsStillYieldOneCombatRecordPerAttendee(): void
    {
        $operation = $this->operation(1);
        $operation->getAars()->add($this->aar($operation, 100));
        $operation->getAars()->add($this->aar($operation, 101));

        $attended = $this->soldier(10);
        $absent = $this->soldier(11);
        $this->service->mark($operation, $absent, false);
        $this->saved = [];
        $this->service->mark($operation, $attended, true);

        $records = $this->combatRecords();
        $this->assertCount(1, $records);
        $this->assertSame($attended, $records[0]->getSoldier());
        $this->assertSame(ServiceRecord::SOURCE_OPERATION, $records[0]->getSourceType());
        $this->assertSame(1, $records[0]->getSourceId());
    }

    public function testSyncRemovesPriorAndLegacyRecordsBeforeRebuilding(): void
    {
        $operation = $this->operation(1);
        $operation->getAars()->add($this->aar($operation, 100));
        $soldier = $this->soldier(10);
        $current = new ServiceRecord($soldier, ServiceRecordType::COMBAT, 'Op');
        $legacy = new ServiceRecord($soldier, ServiceRecordType::COMBAT, 'Op');
        $this->existing['operation:1'] = [$current];
        $this->existing['operation_aar:100'] = [$legacy];

        $this->service->syncCombatRecords($operation);

        $this->assertSame([$current, $legacy], $this->removed);
    }

    public function testRowsIncludeUnitMembersWithoutRsvpAndExcludeOtherUnits(): void
    {
        $unit = new Unit();
        $operation = $this->operation(1);
        $operation->setUnit($unit);

        $rsvpd = $this->soldier(10, $unit);
        $operation->getRsvps()->add(new OperationRSVP($operation, $rsvpd));
        $silent = $this->soldier(11, $unit);
        $elsewhere = $this->soldier(12, new Unit());
        $this->soldierRepository->method('findAttendanceCandidates')->willReturn([$rsvpd, $silent, $elsewhere]);

        $rows = $this->service->rows($operation);

        $this->assertSame([$rsvpd, $silent], array_column($rows, 'soldier'));
        $this->assertNotNull($rows[0]['rsvp']);
        $this->assertNull($rows[1]['rsvp']);
    }

    public function testRowsWithoutOperationUnitIncludeEveryCandidate(): void
    {
        $a = $this->soldier(10);
        $b = $this->soldier(11);
        $this->soldierRepository->method('findAttendanceCandidates')->willReturn([$a, $b]);

        $this->assertSame([$a, $b], array_column($this->service->rows($this->operation(1)), 'soldier'));
    }

    /**
     * @return ServiceRecord[]
     */
    private function combatRecords(): array
    {
        return array_values(array_filter(
            $this->saved,
            static fn (ServiceRecord $r) => $r->getType() === ServiceRecordType::COMBAT,
        ));
    }

    private function operation(int $id): Operation
    {
        $operation = new Operation();
        $operation->setTitle('Op');
        $operation->setStartDateTime(new DateTime('2026-01-01'));
        $this->setId($operation, $id);

        return $operation;
    }

    private function aar(Operation $operation, int $id): OperationAAR
    {
        $aar = new OperationAAR($operation, $this->createStub(User::class));
        $this->setId($aar, $id);

        return $aar;
    }

    private function soldier(int $id, ?Unit $unit = null): SoldierProfile
    {
        $soldier = new SoldierProfile($this->createStub(User::class));
        $this->setId($soldier, $id);
        if ($unit !== null) {
            $soldier->getAssignments()->add(new Assignment($soldier, $unit));
        }

        return $soldier;
    }

    private function setId(object $entity, int $id): void
    {
        (new ReflectionProperty($entity, 'id'))->setValue($entity, $id);
    }
}
