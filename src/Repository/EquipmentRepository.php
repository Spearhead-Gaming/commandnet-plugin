<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Repository;

use Forumify\Core\Repository\AbstractRepository;
use MajesticDev\CommandNet\Entity\Equipment;

/**
 * @extends AbstractRepository<Equipment>
 */
class EquipmentRepository extends AbstractRepository
{
    public static function getEntityClass(): string
    {
        return Equipment::class;
    }
}
