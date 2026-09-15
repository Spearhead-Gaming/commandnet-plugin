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
}
