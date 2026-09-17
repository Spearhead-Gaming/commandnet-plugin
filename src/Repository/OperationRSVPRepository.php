<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Repository;

use Forumify\Core\Repository\AbstractRepository;
use MajesticDev\CommandNet\Entity\OperationRSVP;
use MajesticDev\CommandNet\Entity\SoldierProfile;

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
}
