<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Forumify\Core\Entity\IdentifiableEntityTrait;
use Forumify\Core\Entity\SortableEntityInterface;
use Forumify\Core\Entity\SortableEntityTrait;
use Forumify\Core\Entity\TimestampableEntityTrait;
use MajesticDev\CommandNet\Repository\UnitRepository;

/**
 * A node in the unit hierarchy, e.g. Battalion -> Company -> Platoon -> Squad.
 *
 * Self-referencing parent/children gives you an arbitrarily deep org chart without
 * needing a separate entity per echelon type.
 */
#[ORM\Entity(UnitRepository::class)]
class Unit implements SortableEntityInterface
{
    use IdentifiableEntityTrait;
    use TimestampableEntityTrait;
    use SortableEntityTrait;

    #[ORM\Column(length: 150)]
    private string $name = '';

    /**
     * Short tag shown in rosters and breadcrumbs, e.g. "1-501st", "A Co".
     */
    #[ORM\Column(length: 30)]
    private string $abbreviation = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $insignia = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?self $parent = null;

    /** @var Collection<int, self> */
    #[ORM\OneToMany(mappedBy: 'parent', targetEntity: self::class)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $children;

    /**
     * The soldier currently in command of this unit. Nullable — a billet can sit vacant.
     */
    #[ORM\ManyToOne(targetEntity: SoldierProfile::class)]
    #[ORM\JoinColumn(name: 'commander_id', onDelete: 'SET NULL')]
    private ?SoldierProfile $commander = null;

    /** @var Collection<int, Assignment> */
    #[ORM\OneToMany(mappedBy: 'unit', targetEntity: Assignment::class)]
    private Collection $assignments;

    public function __construct()
    {
        $this->children = new ArrayCollection();
        $this->assignments = new ArrayCollection();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getAbbreviation(): string
    {
        return $this->abbreviation;
    }

    public function setAbbreviation(string $abbreviation): void
    {
        $this->abbreviation = $abbreviation;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getInsignia(): ?string
    {
        return $this->insignia;
    }

    public function setInsignia(?string $insignia): void
    {
        $this->insignia = $insignia;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): void
    {
        $this->parent = $parent;
    }

    /**
     * @return Collection<int, self>
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function getCommander(): ?SoldierProfile
    {
        return $this->commander;
    }

    public function setCommander(?SoldierProfile $commander): void
    {
        $this->commander = $commander;
    }

    /**
     * @return Collection<int, Assignment>
     */
    public function getAssignments(): Collection
    {
        return $this->assignments;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
