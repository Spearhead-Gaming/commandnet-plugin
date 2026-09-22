<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Service;

use MajesticDev\CommandNet\Entity\Enum\ServiceRecordType;
use MajesticDev\CommandNet\Entity\Operation;
use MajesticDev\CommandNet\Entity\OperationRSVP;
use MajesticDev\CommandNet\Entity\ServiceRecord;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Repository\OperationRSVPRepository;
use MajesticDev\CommandNet\Repository\ServiceRecordRepository;
use MajesticDev\CommandNet\Repository\SoldierProfileRepository;

/**
 * Attendance is recorded per soldier, not per RSVP: a soldier who never RSVP'd still gets a
 * row the first time a leader marks them, so no-shows count toward AWOL and unannounced
 * attendees can be credited. Combat records are derived from that attendance (plus "an AAR
 * exists" for the event types EventRules says need one) and rebuilt idempotently, so any
 * number of AARs yields one record per attendee.
 */
class OperationAttendanceService
{
    public function __construct(
        private readonly OperationRSVPRepository $rsvpRepository,
        private readonly SoldierProfileRepository $soldierProfileRepository,
        private readonly ServiceRecordRepository $serviceRecordRepository,
        private readonly AwolService $awolService,
        private readonly EventRules $eventRules,
    ) {
    }

    /**
     * One row per soldier expected at the operation (its unit's primary members, or every
     * active soldier when it has no unit) plus anyone else who RSVP'd.
     * ponytail: exact unit match only, child units aren't included - add if ops target parent units.
     *
     * @return array<int, array{soldier: SoldierProfile, rsvp: ?OperationRSVP}>
     */
    public function rows(Operation $operation): array
    {
        $rows = [];
        foreach ($operation->getRsvps() as $rsvp) {
            $rows[$rsvp->getSoldier()->getId()] = ['soldier' => $rsvp->getSoldier(), 'rsvp' => $rsvp];
        }

        // Patrols and Fun-Days are voluntary: only those who joined (or were marked) are expected.
        if (!$this->eventRules->expectsFullRoster($operation->getType())) {
            return array_values($rows);
        }

        $unit = $operation->getUnit();
        foreach ($this->soldierProfileRepository->findAttendanceCandidates() as $soldier) {
            if (isset($rows[$soldier->getId()])) {
                continue;
            }
            if ($unit === null || $soldier->getPrimaryAssignment()?->getUnit() === $unit) {
                $rows[$soldier->getId()] = ['soldier' => $soldier, 'rsvp' => null];
            }
        }

        return array_values($rows);
    }

    public function mark(Operation $operation, SoldierProfile $soldier, ?bool $attended): void
    {
        $rsvp = $operation->getRsvpFor($soldier);
        if ($rsvp === null) {
            $rsvp = new OperationRSVP($operation, $soldier);
            $operation->getRsvps()->add($rsvp);
        }

        $rsvp->setAttended($attended);
        $this->rsvpRepository->save($rsvp);

        $this->syncCombatRecords($operation);
        $this->awolService->checkAfterAttendanceChange($soldier);
    }

    public function syncCombatRecords(Operation $operation): void
    {
        $sources = [ServiceRecord::SOURCE_OPERATION => [(int) $operation->getId()]];
        foreach ($operation->getAars() as $aar) {
            // Records written before combat records were keyed by operation carry the AAR id.
            $sources[ServiceRecord::SOURCE_OPERATION_AAR][] = (int) $aar->getId();
        }
        foreach ($sources as $type => $ids) {
            foreach ($ids as $id) {
                foreach ($this->serviceRecordRepository->findBySource($type, $id) as $record) {
                    $this->serviceRecordRepository->remove($record, false);
                }
            }
        }

        if ($this->eventRules->creditsCombat($operation)) {
            foreach ($operation->getRsvps() as $rsvp) {
                if ($rsvp->getAttended() !== true) {
                    continue;
                }
                $record = new ServiceRecord($rsvp->getSoldier(), ServiceRecordType::COMBAT, $operation->getTitle());
                $record->setDate($operation->getStartDateTime());
                $record->setSource(ServiceRecord::SOURCE_OPERATION, (int) $operation->getId());
                $this->serviceRecordRepository->save($record, false);
            }
        }

        $this->serviceRecordRepository->flush();
    }
}
