<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Repository;

use Forumify\Core\Repository\AbstractRepository;
use MajesticDev\CommandNet\Entity\Course;
use MajesticDev\CommandNet\Entity\CourseClassStudent;
use MajesticDev\CommandNet\Entity\Enum\CourseResult;
use MajesticDev\CommandNet\Entity\SoldierProfile;

/**
 * @extends AbstractRepository<CourseClassStudent>
 */
class CourseClassStudentRepository extends AbstractRepository
{
    public static function getEntityClass(): string
    {
        return CourseClassStudent::class;
    }

    /**
     * Whether the soldier has passed any class of the course.
     */
    public function hasPassed(SoldierProfile $soldier, Course $course): bool
    {
        return (int)$this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->join('s.courseClass', 'c')
            ->where('s.soldier = :soldier')
            ->andWhere('c.course = :course')
            ->andWhere('s.result = :passed')
            ->setParameter('soldier', $soldier)
            ->setParameter('course', $course)
            ->setParameter('passed', CourseResult::PASSED->value)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }
}
