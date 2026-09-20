<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Service;

use DateTimeImmutable;
use MajesticDev\CommandNet\Entity\Enum\ServiceRecordType;
use MajesticDev\CommandNet\Entity\Qualification;
use MajesticDev\CommandNet\Entity\Rank;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Repository\RankRepository;
use MajesticDev\CommandNet\Repository\SoldierProfileRepository;

/**
 * Checks active soldiers against the requirements of the next rank up: minimum time in
 * their current rank and required qualifications, both configured on the target Rank.
 * Time in grade runs from the latest promotion/demotion record, falling back to the
 * enlistment date for someone who has never changed rank.
 */
class PromotionEligibility
{
    public function __construct(
        private readonly RankRepository $rankRepository,
        private readonly SoldierProfileRepository $soldierProfileRepository,
    ) {
    }

    /**
     * One row per active soldier who has a next rank to be promoted into.
     *
     * @return array<int, array{soldier: SoldierProfile, nextRank: Rank, daysInGrade: ?int, missingDays: int, missingQualifications: Qualification[], eligible: bool}>
     */
    public function evaluateRoster(): array
    {
        $nextRankOf = $this->buildNextRankMap();
        $rows = [];
        foreach ($this->soldierProfileRepository->findRoster() as $soldier) {
            $rank = $soldier->getRank();
            $nextRank = $rank !== null ? ($nextRankOf[$rank->getId()] ?? null) : null;
            if ($nextRank !== null) {
                $rows[] = $this->evaluate($soldier, $nextRank);
            }
        }

        return $rows;
    }

    /**
     * Null when the soldier has no rank or is already at the top one.
     *
     * @return array{soldier: SoldierProfile, nextRank: Rank, daysInGrade: ?int, missingDays: int, missingQualifications: Qualification[], eligible: bool}|null
     */
    public function evaluateSoldier(SoldierProfile $soldier): ?array
    {
        $rank = $soldier->getRank();
        $nextRank = $rank !== null ? ($this->buildNextRankMap()[$rank->getId()] ?? null) : null;

        return $nextRank !== null ? $this->evaluate($soldier, $nextRank) : null;
    }

    /**
     * @return array{soldier: SoldierProfile, nextRank: Rank, daysInGrade: ?int, missingDays: int, missingQualifications: Qualification[], eligible: bool}
     */
    public function evaluate(SoldierProfile $soldier, Rank $nextRank): array
    {
        $daysInGrade = $this->daysInGrade($soldier);
        $required = $nextRank->getMinTimeInGradeDays() ?? 0;
        // Unknown time in grade (no records, no enlistment date) can't satisfy a minimum.
        $missingDays = max(0, $required - ($daysInGrade ?? 0));

        $held = [];
        foreach ($soldier->getQualifications() as $soldierQualification) {
            $held[$soldierQualification->getQualification()->getId()] = true;
        }
        $missing = array_values(array_filter(
            $nextRank->getRequiredQualifications()->toArray(),
            static fn (Qualification $q) => !isset($held[$q->getId()]),
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

    private function daysInGrade(SoldierProfile $soldier): ?int
    {
        $since = $soldier->getEnlistmentDate();
        foreach ($soldier->getServiceRecords() as $record) { // newest first
            if (in_array($record->getType(), [ServiceRecordType::PROMOTION, ServiceRecordType::DEMOTION], true)) {
                $since = $record->getDate();
                break;
            }
        }

        return $since === null
            ? null
            : (int) DateTimeImmutable::createFromInterface($since)->diff(new DateTimeImmutable())->days;
    }

    /**
     * @return array<int, Rank> rank id => the rank one step above it
     */
    private function buildNextRankMap(): array
    {
        $ranks = $this->rankRepository->findBy([], ['position' => 'ASC']);
        $map = [];
        foreach ($ranks as $i => $rank) {
            if (isset($ranks[$i + 1])) {
                $map[$rank->getId()] = $ranks[$i + 1];
            }
        }

        return $map;
    }
}
