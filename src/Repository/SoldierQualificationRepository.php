<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Repository;

use Forumify\Core\Repository\AbstractRepository;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Entity\SoldierQualification;

/**
 * @extends AbstractRepository<SoldierQualification>
 */
class SoldierQualificationRepository extends AbstractRepository
{
    public static function getEntityClass(): string
    {
        return SoldierQualification::class;
    }

    /**
     * Which qualifications each of the given soldiers holds, in one query.
     *
     * @param array<SoldierProfile> $soldiers
     * @return array<int, array<int, true>> soldier id => [qualification id => true]
     */
    public function findHeldQualificationIds(array $soldiers): array
    {
        if ($soldiers === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('sq')
            ->select('IDENTITY(sq.soldier) AS soldierId', 'IDENTITY(sq.qualification) AS qualificationId')
            ->where('sq.soldier IN (:soldiers)')
            ->setParameter('soldiers', array_map(static fn (SoldierProfile $s) => $s->getId(), $soldiers))
            ->getQuery()
            ->getArrayResult();

        $held = [];
        foreach ($rows as $row) {
            $held[(int)$row['soldierId']][(int)$row['qualificationId']] = true;
        }

        return $held;
    }
}
