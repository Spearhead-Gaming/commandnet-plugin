<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Repository;

use Forumify\Core\Repository\AbstractRepository;
use MajesticDev\CommandNet\Entity\OperationAAR;

/**
 * @extends AbstractRepository<OperationAAR>
 */
class OperationAARRepository extends AbstractRepository
{
    public static function getEntityClass(): string
    {
        return OperationAAR::class;
    }
}
