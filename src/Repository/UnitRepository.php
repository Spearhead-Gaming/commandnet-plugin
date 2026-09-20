<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Repository;

use Forumify\Core\Repository\AbstractRepository;
use MajesticDev\CommandNet\Entity\Unit;

/**
 * @extends AbstractRepository<Unit>
 */
class UnitRepository extends AbstractRepository
{
    public static function getEntityClass(): string
    {
        return Unit::class;
    }

    /**
     * Every unit beneath this one in the tree, walked via the already-loaded children
     * collections rather than a recursive SQL query - org trees are small enough that
     * this is simpler than a database-specific recursive CTE.
     *
     * @return array<int>
     */
    public function getDescendantIds(Unit $unit): array
    {
        $ids = [];
        foreach ($unit->getChildren() as $child) {
            $ids[] = $child->getId();
            array_push($ids, ...$this->getDescendantIds($child));
        }

        return $ids;
    }

    /**
     * Matches on name or abbreviation, e.g. for the Discord "/command-net-unit" command's
     * free-text search ("1-501st" should find "1st Battalion, 501st Infantry").
     *
     * @return array<Unit>
     */
    public function findByNameLike(string $name): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.name LIKE :name')
            ->orWhere('u.abbreviation LIKE :name')
            ->setParameter('name', '%' . $name . '%')
            ->setMaxResults(1)
            ->getQuery()
            ->getResult();
    }
}
