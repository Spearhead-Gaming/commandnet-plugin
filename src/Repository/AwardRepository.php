<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Repository;

use Forumify\Core\Repository\AbstractRepository;
use MajesticDev\CommandNet\Entity\Award;

/**
 * @extends AbstractRepository<Award>
 */
class AwardRepository extends AbstractRepository
{
    public static function getEntityClass(): string
    {
        return Award::class;
    }
}
