<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Repository;

use DateTime;
use DateTimeInterface;
use Forumify\Core\Entity\User;
use Forumify\Core\Repository\AbstractRepository;
use MajesticDev\CommandNet\Entity\Enum\OperationStatus;
use MajesticDev\CommandNet\Entity\Enum\OperationType;
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
     * @return array<Operation>
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
     * @return array<Operation>
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

    /**
     * Patrols that have ended and have no AAR yet, oldest first - the ones "due" or "overdue"
     * (EventRules decides which). Cancelled patrols owe none. Pass a leader to limit to theirs.
     *
     * @return array<Operation>
     */
    public function findPatrolsAwaitingAar(?User $leader = null, ?DateTimeInterface $now = null): array
    {
        $qb = $this->createQueryBuilder('o')
            ->where('o.type = :type')
            ->andWhere('o.status != :cancelled')
            ->andWhere('SIZE(o.aars) = 0')
            ->andWhere('COALESCE(o.endDateTime, o.startDateTime) <= :now')
            ->setParameter('type', OperationType::PATROL)
            ->setParameter('cancelled', OperationStatus::CANCELLED)
            ->setParameter('now', $now ?? new DateTime())
            ->orderBy('o.startDateTime', 'ASC');

        if ($leader !== null) {
            $qb->andWhere('o.leader = :leader')->setParameter('leader', $leader);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return array<Operation>
     */
    public function findUpcomingPatrols(int $limit = 10): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.type = :type')
            ->andWhere('o.startDateTime >= :now')
            ->andWhere('o.status != :cancelled')
            ->setParameter('type', OperationType::PATROL)
            ->setParameter('now', new DateTime())
            ->setParameter('cancelled', OperationStatus::CANCELLED)
            ->orderBy('o.startDateTime', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Every patrol a member has led, newest first.
     *
     * @return array<Operation>
     */
    public function findPatrolsLedBy(User $leader): array
    {
        return $this->findBy(['type' => OperationType::PATROL, 'leader' => $leader], ['startDateTime' => 'DESC']);
    }

    /**
     * Looks up an operation by id, but only returns it if it's a patrol - the Discord
     * patrol commands take a bare id from /command-net-patrol-list, and this keeps them
     * from acting on (or leaking the existence of) some other kind of event.
     */
    public function findPatrol(int $id): ?Operation
    {
        $operation = $this->find($id);
        return $operation?->getType() === OperationType::PATROL ? $operation : null;
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
