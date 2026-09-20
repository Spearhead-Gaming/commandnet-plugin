<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Repository;

use Forumify\Core\Repository\AbstractRepository;
use MajesticDev\CommandNet\Entity\Enum\SoldierStatus;
use MajesticDev\CommandNet\Entity\SoldierProfile;

/**
 * @extends AbstractRepository<SoldierProfile>
 */
class SoldierProfileRepository extends AbstractRepository
{
    public static function getEntityClass(): string
    {
        return SoldierProfile::class;
    }

    /**
     * Soldiers who could be expected at an operation: active ones, plus AWOL ones - an AWOL
     * soldier who turns up has to be markable as attended, or nothing could ever clear the flag.
     *
     * @return array<SoldierProfile>
     */
    public function findAttendanceCandidates(): array
    {
        return $this->createQueryBuilder('s')
            ->addSelect('user')
            ->join('s.user', 'user')
            ->where('s.status IN (:statuses)')
            ->setParameter('statuses', [SoldierStatus::ACTIVE, SoldierStatus::AWOL])
            ->orderBy('user.displayName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Active roster, senior-to-junior then alphabetically. Joins rank/user and every
     * assignment with its unit and position up front, so pages that show each soldier's
     * posting (getPrimaryAssignment()) don't lazy-load per row. All assignments are joined,
     * not just the open ones, so the collection is complete rather than partially filled.
     *
     * @return array<SoldierProfile>
     */
    public function findRoster(): array
    {
        return $this->createQueryBuilder('s')
            ->addSelect('soldierRank', 'user', 'assignment', 'assignmentUnit', 'assignmentPosition')
            ->leftJoin('s.rank', 'soldierRank')
            ->leftJoin('s.user', 'user')
            ->leftJoin('s.assignments', 'assignment')
            ->leftJoin('assignment.unit', 'assignmentUnit')
            ->leftJoin('assignment.position', 'assignmentPosition')
            ->where('s.status = :status')
            ->setParameter('status', SoldierStatus::ACTIVE)
            ->orderBy('soldierRank.position', 'DESC')
            ->addOrderBy('user.displayName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Matches on the linked forumify user's display name or username, e.g. for the
     * Discord "/command-net-soldier" command's free-text search.
     *
     * @return array<SoldierProfile>
     */
    public function findByNameLike(string $name): array
    {
        return $this->createQueryBuilder('s')
            ->addSelect('soldierRank', 'user')
            ->leftJoin('s.rank', 'soldierRank')
            ->leftJoin('s.user', 'user')
            ->where('user.displayName LIKE :name')
            ->orWhere('user.username LIKE :name')
            ->setParameter('name', '%' . $name . '%')
            ->setMaxResults(1)
            ->getQuery()
            ->getResult();
    }
}
