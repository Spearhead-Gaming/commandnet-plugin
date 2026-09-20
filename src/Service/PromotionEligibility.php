<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Service;

use DateTimeImmutable;
use DateTimeInterface;
use MajesticDev\CommandNet\Entity\Enum\ServiceRecordType;
use MajesticDev\CommandNet\Entity\Qualification;
use MajesticDev\CommandNet\Entity\Rank;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Repository\RankRepository;
use MajesticDev\CommandNet\Repository\ServiceRecordRepository;
use MajesticDev\CommandNet\Repository\SoldierProfileRepository;
use MajesticDev\CommandNet\Repository\SoldierQualificationRepository;

/**
 * Checks active soldiers against the requirements of the next rank up within their rank
 * group: minimum time in their current rank and required qualifications, both configured on
 * the target Rank. Time in grade runs from the latest promotion/demotion record, falling back
 * to the enlistment date for someone who has never changed rank.
 *
 * evaluateRoster() loads what it needs for the whole roster in a fixed number of queries;
 * evaluateSoldier() is for a single soldier and just reads their own collections.
 */
class PromotionEligibility
{
    public function __construct(
        private readonly RankRepository $rankRepository,
        private readonly SoldierProfileRepository $soldierProfileRepository,
        private readonly SoldierQualificationRepository $soldierQualificationRepository,
        private readonly ServiceRecordRepository $serviceRecordRepository,
    ) {
    }

    /**
     * One row per active soldier who has a next rank to be promoted into.
     *
     * @return array<int, array{soldier: SoldierProfile, nextRank: Rank, daysInGrade: ?int, missingDays: int, missingQualifications: array<Qualification>, eligible: bool}>
     */
    public function evaluateRoster(): array
    {
        $nextRankOf = $this->buildNextRankMap();

        /** @var array<array{SoldierProfile, Rank}> $candidates */
        $candidates = [];
        foreach ($this->soldierProfileRepository->findRoster() as $soldier) {
            $rank = $soldier->getRank();
            $nextRank = $rank !== null ? ($nextRankOf[$rank->getId()] ?? null) : null;
            if ($nextRank !== null) {
                $candidates[] = [$soldier, $nextRank];
            }
        }

        $soldiers = array_column($candidates, 0);
        $held = $this->soldierQualificationRepository->findHeldQualificationIds($soldiers);
        $rankChangeDates = $this->serviceRecordRepository->findLatestRankChangeDates($soldiers);

        $rows = [];
        foreach ($candidates as [$soldier, $nextRank]) {
            $rows[] = $this->assess(
                $soldier,
                $nextRank,
                $held[$soldier->getId()] ?? [],
                $rankChangeDates[$soldier->getId()] ?? $soldier->getEnlistmentDate(),
            );
        }

        return $rows;
    }

    /**
     * Null when the soldier has no rank or is already at the top of their group.
     *
     * @return array{soldier: SoldierProfile, nextRank: Rank, daysInGrade: ?int, missingDays: int, missingQualifications: array<Qualification>, eligible: bool}|null
     */
    public function evaluateSoldier(SoldierProfile $soldier): ?array
    {
        $rank = $soldier->getRank();
        $nextRank = $rank !== null ? ($this->buildNextRankMap()[$rank->getId()] ?? null) : null;
        if ($nextRank === null) {
            return null;
        }

        $held = [];
        foreach ($soldier->getQualifications() as $soldierQualification) {
            $held[$soldierQualification->getQualification()->getId()] = true;
        }

        $since = $soldier->getEnlistmentDate();
        // Service records are ordered newest first.
        foreach ($soldier->getServiceRecords() as $record) {
            if (in_array($record->getType(), [ServiceRecordType::PROMOTION, ServiceRecordType::DEMOTION], true)) {
                $since = $record->getDate();
                break;
            }
        }

        return $this->assess($soldier, $nextRank, $held, $since);
    }

    /**
     * @param array<int, true> $heldQualificationIds
     * @return array{soldier: SoldierProfile, nextRank: Rank, daysInGrade: ?int, missingDays: int, missingQualifications: array<Qualification>, eligible: bool}
     */
    private function assess(
        SoldierProfile $soldier,
        Rank $nextRank,
        array $heldQualificationIds,
        ?DateTimeInterface $inGradeSince,
    ): array {
        $daysInGrade = $inGradeSince === null
            ? null
            : (int) DateTimeImmutable::createFromInterface($inGradeSince)->diff(new DateTimeImmutable())->days;
        $required = $nextRank->getMinTimeInGradeDays() ?? 0;
        // Unknown time in grade (no records, no enlistment date) can't satisfy a minimum.
        $missingDays = max(0, $required - ($daysInGrade ?? 0));

        $missing = array_values(array_filter(
            $nextRank->getRequiredQualifications()->toArray(),
            static fn (Qualification $q) => !isset($heldQualificationIds[$q->getId()]),
        ));

        return [
            'soldier' => $soldier,
            'nextRank' => $nextRank,
            'daysInGrade' => $daysInGrade,
            'missingDays' => $missingDays,
            'missingQualifications' => $missing,
            'eligible' => $missingDays === 0 && $missing === [],
        ];
    }

    /**
     * @return array<int, Rank> rank id => the rank one step above it within the same rank group
     */
    private function buildNextRankMap(): array
    {
        // One ladder per rank group (ranks without a group share a ladder), so the top of one
        // track is never "promoted" into the bottom of the next.
        $ladders = [];
        foreach ($this->rankRepository->findAllWithRequirements() as $rank) {
            $ladders[$rank->getGroup()?->getId() ?? 0][] = $rank;
        }

        $map = [];
        foreach ($ladders as $ladder) {
            foreach ($ladder as $i => $rank) {
                if (isset($ladder[$i + 1])) {
                    $map[$rank->getId()] = $ladder[$i + 1];
                }
            }
        }

        return $map;
    }
}
