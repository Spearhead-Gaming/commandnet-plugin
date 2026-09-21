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
use MajesticDev\CommandNet\Repository\RosterRepository;

/**
 * A named view of the roster made of some units, such as "Combat units" or "Support". The
 * roster page shows a tab per roster; with none defined it shows everyone in one list.
 */
#[ORM\Entity(RosterRepository::class)]
class Roster implements SortableEntityInterface
{
    use IdentifiableEntityTrait;
    use TimestampableEntityTrait;
    use SortableEntityTrait;

    #[ORM\Column(length: 150)]
    private string $name = '';

    #[ORM\Column(type: 'text')]
    private string $description = '';

    /**
     * @var Collection<int, Unit>
     */
    #[ORM\ManyToMany(targetEntity: Unit::class)]
    #[ORM\JoinTable(name: 'roster_unit')]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $units;

    public function __construct()
    {
        $this->units = new ArrayCollection();
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

    /**
     * @return Collection<int, Unit>
     */
    public function getUnits(): Collection
    {
        return $this->units;
    }

    public function addUnit(Unit $unit): void
    {
        if (!$this->units->contains($unit)) {
            $this->units->add($unit);
        }
    }

    public function removeUnit(Unit $unit): void
    {
        $this->units->removeElement($unit);
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
