<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Repository;

use Forumify\Core\Repository\AbstractRepository;
use MajesticDev\CommandNet\Entity\Qualification;

/**
 * @extends AbstractRepository<Qualification>
 */
class QualificationRepository extends AbstractRepository
{
    public static function getEntityClass(): string
    {
        return Qualification::class;
    }
}
