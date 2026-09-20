<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Service;

use DateTime;
use DomainException;
use MajesticDev\CommandNet\Entity\Enum\DischargeKind;
use MajesticDev\CommandNet\Entity\Enum\ServiceRecordType;
use MajesticDev\CommandNet\Entity\ServiceRecord;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Repository\AssignmentRepository;
use MajesticDev\CommandNet\Repository\ServiceRecordRepository;
use MajesticDev\CommandNet\Repository\SoldierProfileRepository;

/**
 * Discharging or retiring a soldier. History is kept rather than cleared: the personnel file,
 * awards, qualifications and service records all stay, the soldier just stops being active -
 * off the roster, out of their unit, and without the forumify roles their unit, rank and AWOL
 * flag gave them. Enlistment can bring them back later.
 */
class DischargeService
{
    public function __construct(
        private readonly SoldierProfileRepository $soldierProfileRepository,
        private readonly ServiceRecordRepository $serviceRecordRepository,
        private readonly AssignmentRepository $assignmentRepository,
        private readonly UnitRoleSyncer $unitRoleSyncer,
        private readonly RankRoleSyncer $rankRoleSyncer,
        private readonly AwolService $awolService,
    ) {
    }

    public function discharge(SoldierProfile $soldier, DischargeKind $kind, ?string $reason, DateTime $date): void
    {
        if (!$soldier->isEnlisted()) {
            throw new DomainException('This soldier has already been discharged.');
        }

        foreach ($soldier->getAssignments() as $assignment) {
            if ($assignment->getEndDate() === null) {
                $assignment->setEndDate($date);
                $this->assignmentRepository->save($assignment, false);
            }
        }

        $soldier->setStatus($kind->status());
        $soldier->setDischargeDate($date);
        $this->soldierProfileRepository->save($soldier);

        $record = new ServiceRecord($soldier, ServiceRecordType::DISCHARGE, $kind->label());
        $record->setDescription($reason);
        $record->setDate($date);
        $this->serviceRecordRepository->save($record);

        // Every open posting is closed, so this revokes every unit role.
        $this->unitRoleSyncer->sync($soldier);
        $this->rankRoleSyncer->sync($soldier, revokeAll: true);
        $this->awolService->revokeRole($soldier);
    }
}
