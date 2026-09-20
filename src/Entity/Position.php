<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Forumify\Core\Entity\IdentifiableEntityTrait;
use MajesticDev\CommandNet\Repository\PositionRepository;

/**
 * A reusable duty title, e.g. "Squad Leader", "Combat Medic", "Radio Operator".
 *
 * Positions aren't tied to a specific unit — the same "Squad Leader" title can be used
 * across every squad. The unit it's actually held in comes from the Assignment.
 */
#[ORM\Entity(PositionRepository::class)]
class Position
{
    use IdentifiableEntityTrait;

    #[ORM\Column(length: 150)]
    private string $title = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    /** @var Collection<int, Equipment> */
    #[ORM\ManyToMany(targetEntity: Equipment::class)]
    #[ORM\JoinTable(name: 'position_primary_weapon')]
    private Collection $primaryWeapons;

    /** @var Collection<int, Equipment> */
    #[ORM\ManyToMany(targetEntity: Equipment::class)]
    #[ORM\JoinTable(name: 'position_secondary_weapon')]
    private Collection $secondaryWeapons;

    public function __construct()
    {
        $this->primaryWeapons = new ArrayCollection();
        $this->secondaryWeapons = new ArrayCollection();
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    /**
     * @return Collection<int, Equipment>
     */
    public function getPrimaryWeapons(): Collection
    {
        return $this->primaryWeapons;
    }

    public function addPrimaryWeapon(Equipment $weapon): void
    {
        if (!$this->primaryWeapons->contains($weapon)) {
            $this->primaryWeapons->add($weapon);
        }
    }

    public function removePrimaryWeapon(Equipment $weapon): void
    {
        $this->primaryWeapons->removeElement($weapon);
    }

    /**
     * @return Collection<int, Equipment>
     */
    public function getSecondaryWeapons(): Collection
    {
        return $this->secondaryWeapons;
    }

    public function addSecondaryWeapon(Equipment $weapon): void
    {
        if (!$this->secondaryWeapons->contains($weapon)) {
            $this->secondaryWeapons->add($weapon);
        }
    }

    public function removeSecondaryWeapon(Equipment $weapon): void
    {
        $this->secondaryWeapons->removeElement($weapon);
    }

    public function __toString(): string
    {
        return $this->title;
    }
}
