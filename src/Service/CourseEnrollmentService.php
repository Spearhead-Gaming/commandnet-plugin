<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Service;

use DomainException;
use MajesticDev\CommandNet\Entity\CourseClass;
use MajesticDev\CommandNet\Entity\CourseClassStudent;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Repository\CourseClassStudentRepository;

/**
 * Enrolling in and withdrawing from a class, and the rules for who may: enlisted, before the
 * class starts, a free slot, the course minimum rank (while ranks are enabled), and every prerequisite course passed.
 */
class CourseEnrollmentService
{
    public function __construct(
        private readonly CourseClassStudentRepository $studentRepository,
        private readonly RankSettings $rankSettings,
    )
    {
    }

    /**
     * Why this soldier can not enrol right now, or null if they can.
     */
    public function ineligibleReason(SoldierProfile $soldier, CourseClass $class): ?string
    {
        if (!$soldier->isEnlisted()) {
            return 'Only enlisted personnel can enrol.';
        }
        if ($class->isProcessed() || $class->hasStarted()) {
            return 'This class has already started.';
        }
        if ($class->getStudentFor($soldier) !== null) {
            return 'You are already enrolled in this class.';
        }
        if ($class->isFull()) {
            return 'This class is full.';
        }

        $course = $class->getCourse();
        $minimumRank = $course->getMinimumRank();
        if ($this->rankSettings->isEnabled() && $minimumRank !== null && ($soldier->getRank()?->getPosition() ?? -1) < $minimumRank->getPosition()) {
            return 'This course needs the rank ' . $minimumRank->getName() . ' or higher.';
        }

        foreach ($course->getPrerequisites() as $prerequisite) {
            if (!$this->studentRepository->hasPassed($soldier, $prerequisite)) {
                return 'You need to pass ' . $prerequisite->getName() . ' first.';
            }
        }

        return null;
    }

    public function enroll(SoldierProfile $soldier, CourseClass $class): void
    {
        $reason = $this->ineligibleReason($soldier, $class);
        if ($reason !== null) {
            throw new DomainException($reason);
        }

        $student = new CourseClassStudent($class, $soldier);
        $class->getStudents()->add($student);
        $this->studentRepository->save($student);
    }

    public function withdraw(SoldierProfile $soldier, CourseClass $class): void
    {
        if ($class->isProcessed() || $class->hasStarted()) {
            throw new DomainException('This class has already started.');
        }

        $student = $class->getStudentFor($soldier);
        if ($student === null) {
            return;
        }

        $class->getStudents()->removeElement($student);
        $this->studentRepository->remove($student);
    }
}
