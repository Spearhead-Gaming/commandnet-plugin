<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Entity;

use Doctrine\ORM\Mapping as ORM;
use Forumify\Core\Entity\IdentifiableEntityTrait;
use Forumify\Core\Entity\Role;
use MajesticDev\CommandNet\Repository\SpecialtyRepository;

/**
 * A soldier's trade, e.g. "Combat Medic", "Radio Operator", "Marksman" - one per soldier, set
 * on their personnel profile. Unlike a Position (a duty within a unit) it follows the soldier
 * between units. May carry a forumify Role, held while it is their specialty.
 */
#[ORM\Entity(SpecialtyRepository::class)]
class Specialty
{
    use IdentifiableEntityTrait;

    #[ORM\Column(length: 100)]
    private string $name = '';

    #[ORM\Column(length: 20)]
    private string $abbreviation = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    /**
     * Granted to a soldier with this specialty (see SpecialtyRoleSyncer). Mapping it to a
     * Discord role is the job of the Discord plugin.
     */
    #[ORM\ManyToOne(targetEntity: Role::class)]
    #[ORM\JoinColumn(name: 'role_id', onDelete: 'SET NULL')]
    private ?Role $role = null;

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

    public function getRole(): ?Role
    {
        return $this->role;
    }

    public function setRole(?Role $role): void
    {
        $this->role = $role;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
