<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Repository;

use Forumify\Core\Repository\AbstractRepository;
use MajesticDev\CommandNet\Entity\SoldierQualification;

/**
 * @extends AbstractRepository<SoldierQualification>
 */
class SoldierQualificationRepository extends AbstractRepository
{
    public static function getEntityClass(): string
    {
        return SoldierQualification::class;
    }
}
