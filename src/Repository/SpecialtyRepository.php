<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Repository;

use Forumify\Core\Repository\AbstractRepository;
use MajesticDev\CommandNet\Entity\Specialty;

/**
 * @extends AbstractRepository<Specialty>
 */
class SpecialtyRepository extends AbstractRepository
{
    public static function getEntityClass(): string
    {
        return Specialty::class;
    }
}
