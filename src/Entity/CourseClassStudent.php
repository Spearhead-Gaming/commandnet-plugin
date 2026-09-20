<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Entity;

use Doctrine\ORM\Mapping as ORM;
use Forumify\Core\Entity\IdentifiableEntityTrait;
use MajesticDev\CommandNet\Entity\Enum\CourseResult;
use MajesticDev\CommandNet\Repository\CourseClassStudentRepository;

/**
 * A soldier enrolled in a class. The result stays empty until the class is over.
 */
#[ORM\Entity(CourseClassStudentRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_course_class_student', columns: ['course_class_id', 'soldier_id'])]
class CourseClassStudent
{
    use IdentifiableEntityTrait;

    #[ORM\ManyToOne(targetEntity: CourseClass::class, inversedBy: 'students')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private CourseClass $courseClass;

    #[ORM\ManyToOne(targetEntity: SoldierProfile::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private SoldierProfile $soldier;

    #[ORM\Column(length: 20, nullable: true, enumType: CourseResult::class)]
    private ?CourseResult $result = null;

    public function __construct(CourseClass $courseClass, SoldierProfile $soldier)
    {
        $this->courseClass = $courseClass;
        $this->soldier = $soldier;
    }

    public function getCourseClass(): CourseClass
    {
        return $this->courseClass;
    }

    public function getSoldier(): SoldierProfile
    {
        return $this->soldier;
    }

    public function getResult(): ?CourseResult
    {
        return $this->result;
    }

    public function setResult(?CourseResult $result): void
    {
        $this->result = $result;
    }
}
