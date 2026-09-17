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
     * Active roster, senior-to-junior then alphabetically. Joins rank/user up front so
     * the roster template isn't triggering N+1 lazy loads per row.
     *
     * @return SoldierProfile[]
     */
    public function findRoster(): array
    {
        return $this->createQueryBuilder('s')
            ->addSelect('soldierRank', 'user')
            ->leftJoin('s.rank', 'soldierRank')
            ->leftJoin('s.user', 'user')
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
     * @return SoldierProfile[]
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
