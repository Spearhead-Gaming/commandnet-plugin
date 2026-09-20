<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Repository;

use Forumify\Core\Repository\AbstractRepository;
use MajesticDev\CommandNet\Entity\Rank;

/**
 * @extends AbstractRepository<Rank>
 */
class RankRepository extends AbstractRepository
{
    public static function getEntityClass(): string
    {
        return Rank::class;
    }

    /**
     * Every rank in ladder order with its required qualifications already loaded, so checking
     * promotion requirements doesn't load them one rank at a time.
     *
     * @return array<Rank>
     */
    public function findAllWithRequirements(): array
    {
        return $this->createQueryBuilder('r')
            ->addSelect('q')
            ->leftJoin('r.requiredQualifications', 'q')
            ->orderBy('r.position', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
