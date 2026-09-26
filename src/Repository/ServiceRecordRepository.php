<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Repository;

use DateTimeImmutable;
use Forumify\Core\Repository\AbstractRepository;
use MajesticDev\CommandNet\Entity\Enum\ServiceRecordType;
use MajesticDev\CommandNet\Entity\ServiceRecord;
use MajesticDev\CommandNet\Entity\SoldierProfile;

/**
 * @extends AbstractRepository<ServiceRecord>
 */
class ServiceRecordRepository extends AbstractRepository
{
    public static function getEntityClass(): string
    {
        return ServiceRecord::class;
    }

    /**
     * The date of each given soldier's latest promotion or demotion, in one query. Soldiers who
     * have never changed rank are absent from the result.
     *
     * @param array<SoldierProfile> $soldiers
     * @return array<int, DateTimeImmutable> soldier id => date
     */
    public function findLatestRankChangeDates(array $soldiers): array
    {
        if ($soldiers === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('sr')
            ->select('IDENTITY(sr.soldier) AS soldierId', 'MAX(sr.date) AS latest')
            ->where('sr.soldier IN (:soldiers)')
            ->andWhere('sr.type IN (:types)')
            ->setParameter('soldiers', array_map(static fn (SoldierProfile $s) => $s->getId(), $soldiers))
            ->setParameter('types', [ServiceRecordType::PROMOTION->value, ServiceRecordType::DEMOTION->value])
            ->groupBy('sr.soldier')
            ->getQuery()
            ->getArrayResult();

        $dates = [];
        foreach ($rows as $row) {
            $dates[(int)$row['soldierId']] = new DateTimeImmutable((string)$row['latest']);
        }

        return $dates;
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

    /**
     * Removes what a deleted operation left on personnel files: the combat records credited to
     * it, and the records written by each of its AARs. Records point at their source by id, with
     * no database link, so nothing removes them when the operation goes.
     *
     * @param array<int> $aarIds the operation's AAR ids, taken before it was deleted
     */
    public function deleteForOperation(int $operationId, array $aarIds): void
    {
        $query = $this->createQueryBuilder('record')
            ->delete()
            ->where('record.sourceType = :operation AND record.sourceId = :operationId')
            ->setParameter('operation', ServiceRecord::SOURCE_OPERATION)
            ->setParameter('operationId', $operationId);

        if ($aarIds !== []) {
            $query
                ->orWhere('record.sourceType = :aar AND record.sourceId IN (:aarIds)')
                ->setParameter('aar', ServiceRecord::SOURCE_OPERATION_AAR)
                ->setParameter('aarIds', $aarIds);
        }

        $query->getQuery()->execute();
    }
}
