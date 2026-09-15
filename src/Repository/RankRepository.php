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
}
