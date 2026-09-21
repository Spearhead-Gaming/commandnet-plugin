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
            ->addSelect('soldierRank', 'user', 'specialty', 'assignment', 'assignmentUnit', 'assignmentPosition')
            ->leftJoin('s.rank', 'soldierRank')
            ->leftJoin('s.specialty', 'specialty')
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
     * Enlisted soldiers with a Steam ID, senior first, for the Squad XML export. Discharged and
     * retired soldiers are left out; assignments and units are joined up front.
     *
     * @return array<SoldierProfile>
     */
    public function findForSquadXml(): array
    {
        return $this->createQueryBuilder('s')
            ->addSelect('user', 'soldierRank', 'assignment', 'assignmentUnit')
            ->join('s.user', 'user')
            ->leftJoin('s.rank', 'soldierRank')
            ->leftJoin('s.assignments', 'assignment')
            ->leftJoin('assignment.unit', 'assignmentUnit')
            ->where('s.steamId IS NOT NULL')
            ->andWhere("s.steamId != ''")
            ->andWhere('s.status NOT IN (:gone)')
            ->setParameter('gone', [SoldierStatus::DISCHARGED->value, SoldierStatus::RETIRED->value])
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
    /**
     * Loads the assignments (with their units) of these soldiers in one query, so reading
     * getPrimaryAssignment() on each of them does not query per soldier. Fetch-joining the
     * collection into the page query itself would break its LIMIT, hence a second query.
     *
     * @param array<SoldierProfile> $soldiers
     */
    public function loadAssignmentsFor(array $soldiers): void
    {
        if ($soldiers === []) {
            return;
        }

        $this->createQueryBuilder('s')
            ->select('s', 'assignment', 'assignmentUnit')
            ->leftJoin('s.assignments', 'assignment')
            ->leftJoin('assignment.unit', 'assignmentUnit')
            ->where('s IN (:soldiers)')
            ->setParameter('soldiers', $soldiers)
            ->getQuery()
            ->getResult();
    }

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
