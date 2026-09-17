<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Repository;

use Forumify\Core\Repository\AbstractRepository;
use MajesticDev\CommandNet\Entity\OperationRSVP;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Entity\Unit;

/**
 * @extends AbstractRepository<OperationRSVP>
 */
class OperationRSVPRepository extends AbstractRepository
{
    public static function getEntityClass(): string
    {
        return OperationRSVP::class;
    }

    /**
     * Every RSVP for this soldier where attendance was actually taken, newest operation
     * first - the raw material the attendance dashboard summarizes. RSVPs nobody has
     * marked attended/absent yet are excluded rather than counted as no-shows.
     *
     * @return OperationRSVP[]
     */
    public function findAttendanceHistory(SoldierProfile $soldier): array
    {
        return $this->createQueryBuilder('r')
            ->addSelect('operation')
            ->join('r.operation', 'operation')
            ->where('r.soldier = :soldier')
            ->andWhere('r.attended IS NOT NULL')
            ->setParameter('soldier', $soldier)
            ->orderBy('operation.startDateTime', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Same as findAttendanceHistory(), but for AWOL detection: only operations tied to the
     * given unit count toward the streak (an operation open to everyone, with no unit set,
     * still counts) - so transferring units resets the miss streak instead of carrying over
     * absences racked up in a different unit. Falls back to the unscoped history when the
     * soldier currently has no unit at all.
     *
     * @return OperationRSVP[]
     */
    public function findAttendanceHistoryForUnit(SoldierProfile $soldier, ?Unit $unit): array
    {
        $qb = $this->createQueryBuilder('r')
            ->addSelect('operation')
            ->join('r.operation', 'operation')
            ->where('r.soldier = :soldier')
            ->andWhere('r.attended IS NOT NULL')
            ->setParameter('soldier', $soldier)
            ->orderBy('operation.startDateTime', 'DESC');

        if ($unit !== null) {
            $qb->andWhere('operation.unit IS NULL OR operation.unit = :unit')
                ->setParameter('unit', $unit);
        }

        return $qb->getQuery()->getResult();
    }
}
