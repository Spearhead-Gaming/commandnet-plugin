<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Repository;

use Forumify\Core\Repository\AbstractRepository;
use MajesticDev\CommandNet\Entity\Qualification;

/**
 * @extends AbstractRepository<Qualification>
 */
class QualificationRepository extends AbstractRepository
{
    public static function getEntityClass(): string
    {
        return Qualification::class;
    }

    /**
     * Qualifications for the public board, grouped by tier. Qualifications with no tier
     * are excluded - the entity's own docblock treats a null tier as "don't show this here".
     *
     * @return array<string, array<Qualification>> keyed by QualificationTier::value, in tier order
     */
    public function findAllForBoard(): array
    {
        $qualifications = $this->createQueryBuilder('q')
            ->where('q.tier IS NOT NULL')
            ->orderBy('q.tier', 'ASC')
            ->addOrderBy('q.position', 'ASC')
            ->getQuery()
            ->getResult();

        $grouped = [];
        foreach ($qualifications as $qualification) {
            $grouped[$qualification->getTier()->value][] = $qualification;
        }

        return $grouped;
    }
}
