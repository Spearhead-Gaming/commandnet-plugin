<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Repository;

use Forumify\Core\Repository\AbstractRepository;
use MajesticDev\CommandNet\Entity\RankGroup;

/**
 * @extends AbstractRepository<RankGroup>
 */
class RankGroupRepository extends AbstractRepository
{
    public static function getEntityClass(): string
    {
        return RankGroup::class;
    }
}
