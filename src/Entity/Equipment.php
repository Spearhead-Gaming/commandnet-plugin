<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Entity;

use Doctrine\ORM\Mapping as ORM;
use Forumify\Core\Entity\IdentifiableEntityTrait;
use MajesticDev\CommandNet\Entity\Enum\EquipmentType;
use MajesticDev\CommandNet\Repository\EquipmentRepository;

/**
 * A weapon or vehicle the unit can field. Positions list the weapons their holder may use and
 * units list the vehicles they have; a soldier loadout is worked out from those, see SoldierLoadout.
 */
#[ORM\Entity(EquipmentRepository::class)]
class Equipment
{
    use IdentifiableEntityTrait;

    #[ORM\Column(length: 150)]
    private string $name = '';

    #[ORM\Column(length: 20, enumType: EquipmentType::class)]
    private EquipmentType $type = EquipmentType::PRIMARY_WEAPON;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getType(): EquipmentType
    {
        return $this->type;
    }

    public function setType(EquipmentType $type): void
    {
        $this->type = $type;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
