<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Repository;

use Forumify\Core\Repository\AbstractRepository;
use MajesticDev\CommandNet\Entity\OperationRSVP;

/**
 * @extends AbstractRepository<OperationRSVP>
 */
class OperationRSVPRepository extends AbstractRepository
{
    public static function getEntityClass(): string
    {
        return OperationRSVP::class;
    }
}
