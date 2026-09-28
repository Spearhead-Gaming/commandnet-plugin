<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Repository;

use Forumify\Core\Repository\AbstractRepository;
use MajesticDev\CommandNet\Entity\Squad;

/**
 * @extends AbstractRepository<Squad>
 */
class SquadRepository extends AbstractRepository
{
    public static function getEntityClass(): string
    {
        return Squad::class;
    }
}
