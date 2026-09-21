<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Entity\Enum;

enum EquipmentType: string
{
    case PRIMARY_WEAPON = 'primary_weapon';
    case SECONDARY_WEAPON = 'secondary_weapon';
    case VEHICLE = 'vehicle';

    public function label(): string
    {
        return match ($this) {
            self::PRIMARY_WEAPON => 'Primary Weapon',
            self::SECONDARY_WEAPON => 'Secondary Weapon',
            self::VEHICLE => 'Vehicle',
        };
    }
}
