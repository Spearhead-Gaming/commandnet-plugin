<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Repository;

use Forumify\Core\Repository\AbstractRepository;
use MajesticDev\CommandNet\Entity\ServiceRecord;

/**
 * @extends AbstractRepository<ServiceRecord>
 */
class ServiceRecordRepository extends AbstractRepository
{
    public static function getEntityClass(): string
    {
        return ServiceRecord::class;
    }
}
