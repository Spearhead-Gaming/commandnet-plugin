<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Repository;

use Forumify\Core\Repository\AbstractRepository;
use MajesticDev\CommandNet\Entity\SoldierAward;

/**
 * @extends AbstractRepository<SoldierAward>
 */
class SoldierAwardRepository extends AbstractRepository
{
    public static function getEntityClass(): string
    {
        return SoldierAward::class;
    }
}
