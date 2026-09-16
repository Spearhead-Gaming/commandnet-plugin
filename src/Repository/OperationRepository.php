<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Repository;

use DateTime;
use Forumify\Core\Repository\AbstractRepository;
use MajesticDev\CommandNet\Entity\Enum\OperationStatus;
use MajesticDev\CommandNet\Entity\Operation;

/**
 * @extends AbstractRepository<Operation>
 */
class OperationRepository extends AbstractRepository
{
    public static function getEntityClass(): string
    {
        return Operation::class;
    }

    /**
     * @return Operation[]
     */
    public function findUpcoming(int $limit = 10): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.startDateTime >= :now')
            ->andWhere('o.status != :cancelled')
            ->setParameter('now', new DateTime())
            ->setParameter('cancelled', OperationStatus::CANCELLED)
            ->orderBy('o.startDateTime', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Operation[]
     */
    public function findPast(int $limit = 20): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.startDateTime < :now')
            ->setParameter('now', new DateTime())
            ->orderBy('o.startDateTime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countUpcoming(): int
    {
        return (int)$this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->where('o.startDateTime >= :now')
            ->andWhere('o.status != :cancelled')
            ->setParameter('now', new DateTime())
            ->setParameter('cancelled', OperationStatus::CANCELLED)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findNextUpcoming(): ?Operation
    {
        return $this->createQueryBuilder('o')
            ->where('o.startDateTime >= :now')
            ->andWhere('o.status != :cancelled')
            ->setParameter('now', new DateTime())
            ->setParameter('cancelled', OperationStatus::CANCELLED)
            ->orderBy('o.startDateTime', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
