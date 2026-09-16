<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Repository;

use Forumify\Core\Repository\AbstractRepository;
use MajesticDev\CommandNet\Entity\ReportIn;
use MajesticDev\CommandNet\Entity\SoldierProfile;

/**
 * @extends AbstractRepository<ReportIn>
 */
class ReportInRepository extends AbstractRepository
{
    public static function getEntityClass(): string
    {
        return ReportIn::class;
    }

    /**
     * Used to recompute SoldierProfile::$lastReportIn after a correction removes what was
     * the most recent entry, so the cached column doesn't point at a deleted record.
     */
    public function findLatestFor(SoldierProfile $soldier): ?ReportIn
    {
        return $this->createQueryBuilder('r')
            ->where('r.soldier = :soldier')
            ->setParameter('soldier', $soldier)
            ->orderBy('r.reportedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return ReportIn[] newest first
     */
    public function findHistoryFor(SoldierProfile $soldier, int $limit = 20): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.soldier = :soldier')
            ->setParameter('soldier', $soldier)
            ->orderBy('r.reportedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}