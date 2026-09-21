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

    public function findOneBySource(string $sourceType, int $sourceId): ?ServiceRecord
    {
        return $this->findOneBy(['sourceType' => $sourceType, 'sourceId' => $sourceId]);
    }

    /**
     * @return array<ServiceRecord>
     */
    public function findBySource(string $sourceType, int $sourceId): array
    {
        return $this->findBy(['sourceType' => $sourceType, 'sourceId' => $sourceId]);
    }
}
