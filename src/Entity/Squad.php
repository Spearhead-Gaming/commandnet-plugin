<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Forumify\Core\Entity\IdentifiableEntityTrait;
use MajesticDev\CommandNet\Repository\SquadRepository;

/**
 * A grouping within a Unit that isn't itself a command - a squad or a team, e.g. "Squad 1"
 * inside "Detachment 7", or "Team 1" inside "Squad 1". Deliberately not a Unit: a squad/team
 * has no commander billet distinct from its own positions, no insignia, no Discord role grant,
 * no vehicles, and never shows up in the Arma 3 ORBAT export - carrying Unit's full field set
 * for something this small only added noise. Self-referencing the same way Unit is, but only
 * two levels deep in practice (a squad directly under a Unit, a team under that squad).
 */
#[ORM\Entity(SquadRepository::class)]
class Squad implements HasPositions
{
    use IdentifiableEntityTrait;

    #[ORM\Column(length: 150)]
    private string $name = '';

    #[ORM\ManyToOne(targetEntity: Unit::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Unit $unit;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?self $parent = null;

    /** @var Collection<int, self> */
    #[ORM\OneToMany(mappedBy: 'parent', targetEntity: self::class)]
    private Collection $children;

    /**
     * The billets this squad/team is expected to hold, e.g. "Squad Leader"/"Team Leader" -
     * same shared, reusable catalog Unit::positions draws from.
     *
     * @var Collection<int, Position>
     */
    #[ORM\ManyToMany(targetEntity: Position::class)]
    #[ORM\JoinTable(name: 'squad_position')]
    private Collection $positions;

    public function __construct(Unit $unit)
    {
        $this->unit = $unit;
        $this->children = new ArrayCollection();
        $this->positions = new ArrayCollection();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getUnit(): Unit
    {
        return $this->unit;
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

    /**
     * @return Collection<int, Position>
     */
    public function getPositions(): Collection
    {
        return $this->positions;
    }

    public function addPosition(Position $position): void
    {
        if (!$this->positions->contains($position)) {
            $this->positions->add($position);
        }
    }

    public function removePosition(Position $position): void
    {
        $this->positions->removeElement($position);
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
