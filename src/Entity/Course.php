<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Forumify\Core\Entity\IdentifiableEntityTrait;
use MajesticDev\CommandNet\Repository\CourseRepository;

/**
 * A course soldiers can take. Passing a class of it grants its qualifications. It can require a
 * minimum rank and other courses passed first.
 */
#[ORM\Entity(CourseRepository::class)]
class Course
{
    use IdentifiableEntityTrait;

    #[ORM\Column(length: 150)]
    private string $name = '';

    #[ORM\Column(type: 'text')]
    private string $description = '';

    #[ORM\ManyToOne(targetEntity: Rank::class)]
    #[ORM\JoinColumn(name: 'minimum_rank_id', onDelete: 'SET NULL')]
    private ?Rank $minimumRank = null;

    /**
     * @var Collection<int, Course>
     */
    #[ORM\ManyToMany(targetEntity: self::class)]
    #[ORM\JoinTable(name: 'course_prerequisite')]
    #[ORM\JoinColumn(name: 'course_id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'prerequisite_id', onDelete: 'CASCADE')]
    private Collection $prerequisites;

    /**
     * @var Collection<int, Qualification>
     */
    #[ORM\ManyToMany(targetEntity: Qualification::class)]
    #[ORM\JoinTable(name: 'course_qualification')]
    private Collection $qualifications;

    public function __construct()
    {
        $this->prerequisites = new ArrayCollection();
        $this->qualifications = new ArrayCollection();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getMinimumRank(): ?Rank
    {
        return $this->minimumRank;
    }

    public function setMinimumRank(?Rank $minimumRank): void
    {
        $this->minimumRank = $minimumRank;
    }

    /**
     * @return Collection<int, Course>
     */
    public function getPrerequisites(): Collection
    {
        return $this->prerequisites;
    }

    public function addPrerequisite(Course $course): void
    {
        if (!$this->prerequisites->contains($course)) {
            $this->prerequisites->add($course);
        }
    }

    public function removePrerequisite(Course $course): void
    {
        $this->prerequisites->removeElement($course);
    }

    /**
     * @return Collection<int, Qualification>
     */
    public function getQualifications(): Collection
    {
        return $this->qualifications;
    }

    public function addQualification(Qualification $qualification): void
    {
        if (!$this->qualifications->contains($qualification)) {
            $this->qualifications->add($qualification);
        }
    }

    public function removeQualification(Qualification $qualification): void
    {
        $this->qualifications->removeElement($qualification);
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
