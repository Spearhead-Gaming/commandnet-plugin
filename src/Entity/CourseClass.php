<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Entity;

use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Forumify\Core\Entity\IdentifiableEntityTrait;
use MajesticDev\CommandNet\Repository\CourseClassRepository;

/**
 * One scheduled run of a course. Soldiers enrol until it starts; afterwards the results are
 * recorded once and processed (see CourseResultsService), which grants the course qualifications.
 */
#[ORM\Entity(CourseClassRepository::class)]
class CourseClass
{
    use IdentifiableEntityTrait;

    #[ORM\ManyToOne(targetEntity: Course::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Course $course;

    #[ORM\Column(type: 'datetime')]
    private DateTime $startsAt;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $endsAt = null;

    #[ORM\Column(nullable: true)]
    private ?int $studentSlots = null;

    #[ORM\ManyToOne(targetEntity: SoldierProfile::class)]
    #[ORM\JoinColumn(name: 'instructor_id', onDelete: 'SET NULL')]
    private ?SoldierProfile $instructor = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $processed = false;

    /**
     * @var Collection<int, CourseClassStudent>
     */
    #[ORM\OneToMany(targetEntity: CourseClassStudent::class, mappedBy: 'courseClass', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $students;

    public function __construct()
    {
        $this->students = new ArrayCollection();
        $this->startsAt = new DateTime('+1 week');
    }

    public function getCourse(): Course
    {
        return $this->course;
    }

    public function setCourse(Course $course): void
    {
        $this->course = $course;
    }

    public function getStartsAt(): DateTime
    {
        return $this->startsAt;
    }

    public function setStartsAt(DateTime $startsAt): void
    {
        $this->startsAt = $startsAt;
    }

    public function getEndsAt(): ?DateTime
    {
        return $this->endsAt;
    }

    public function setEndsAt(?DateTime $endsAt): void
    {
        $this->endsAt = $endsAt;
    }

    public function getStudentSlots(): ?int
    {
        return $this->studentSlots;
    }

    public function setStudentSlots(?int $studentSlots): void
    {
        $this->studentSlots = $studentSlots;
    }

    public function getInstructor(): ?SoldierProfile
    {
        return $this->instructor;
    }

    public function setInstructor(?SoldierProfile $instructor): void
    {
        $this->instructor = $instructor;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): void
    {
        $this->notes = $notes;
    }

    public function isProcessed(): bool
    {
        return $this->processed;
    }

    public function markProcessed(): void
    {
        $this->processed = true;
    }

    /**
     * @return Collection<int, CourseClassStudent>
     */
    public function getStudents(): Collection
    {
        return $this->students;
    }

    public function getStudentFor(SoldierProfile $soldier): ?CourseClassStudent
    {
        foreach ($this->students as $student) {
            if ($student->getSoldier() === $soldier) {
                return $student;
            }
        }

        return null;
    }

    public function isFull(): bool
    {
        return $this->studentSlots !== null && $this->students->count() >= $this->studentSlots;
    }

    public function hasStarted(): bool
    {
        return $this->startsAt <= new DateTime();
    }

    public function __toString(): string
    {
        return $this->course->getName() . ' - ' . $this->startsAt->format('Y-m-d H:i');
    }
}
