<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Forumify\Core\Entity\IdentifiableEntityTrait;
use Forumify\Core\Entity\SortableEntityInterface;
use Forumify\Core\Entity\SortableEntityTrait;
use MajesticDev\CommandNet\Entity\Enum\QualificationTier;
use MajesticDev\CommandNet\Repository\QualificationRepository;

/**
 * A skill badge/qualification soldiers can earn, e.g. "Combat Medic", "Jumpmaster".
 * The Courses module can be configured to auto-issue one of these on graduation.
 */
#[ORM\Entity(QualificationRepository::class)]
class Qualification implements SortableEntityInterface
{
    use IdentifiableEntityTrait;
    use SortableEntityTrait;

    #[ORM\Column(length: 150)]
    private string $name = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $icon = null;

    /**
     * Groups this qualification on the public qualifications board. Null means it doesn't
     * appear there at all (e.g. a legacy qualification kept only for existing soldiers).
     */
    #[ORM\Column(length: 20, enumType: QualificationTier::class, nullable: true)]
    private ?QualificationTier $tier = null;

    /**
     * Units this qualification is restricted to. An empty collection means it's available
     * to every unit - most qualifications - rather than requiring every one of them to be
     * explicitly listed.
     *
     * @var Collection<int, Unit>
     */
    #[ORM\ManyToMany(targetEntity: Unit::class)]
    #[ORM\JoinTable(name: 'qualification_unit')]
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(?string $icon): void
    {
        $this->icon = $icon;
    }

    public function getTier(): ?QualificationTier
    {
        return $this->tier;
    }

    public function setTier(?QualificationTier $tier): void
    {
        $this->tier = $tier;
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

    /**
     * True for every unit when this qualification isn't restricted to specific ones.
     */
    public function isAvailableToUnit(?Unit $unit): bool
    {
        return $this->units->isEmpty() || ($unit !== null && $this->units->contains($unit));
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
