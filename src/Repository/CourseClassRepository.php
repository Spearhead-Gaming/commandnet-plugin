<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Repository;

use DateTime;
use Forumify\Core\Repository\AbstractRepository;
use MajesticDev\CommandNet\Entity\CourseClass;

/**
 * @extends AbstractRepository<CourseClass>
 */
class CourseClassRepository extends AbstractRepository
{
    public static function getEntityClass(): string
    {
        return CourseClass::class;
    }

    /**
     * Classes that have not run yet, soonest first, with their course loaded.
     *
     * @return array<CourseClass>
     */
    public function findUpcoming(): array
    {
        return $this->createQueryBuilder('c')
            ->addSelect('course')
            ->join('c.course', 'course')
            ->where('c.startsAt > :now')
            ->setParameter('now', new DateTime())
            ->orderBy('c.startsAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
