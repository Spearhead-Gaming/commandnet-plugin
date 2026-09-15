<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Repository;

use Forumify\Core\Repository\AbstractRepository;
use MajesticDev\CommandNet\Entity\Position;

/**
 * @extends AbstractRepository<Position>
 */
class PositionRepository extends AbstractRepository
{
    public static function getEntityClass(): string
    {
        return Position::class;
    }
}
