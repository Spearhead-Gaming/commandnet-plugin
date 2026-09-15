<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Entity\Enum;

enum SoldierStatus: string
{
    case ACTIVE = 'active';
    case LOA = 'loa';
    case AWOL = 'awol';
    case DISCHARGED = 'discharged';
    case RETIRED = 'retired';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::LOA => 'Leave of Absence',
            self::AWOL => 'AWOL',
            self::DISCHARGED => 'Discharged',
            self::RETIRED => 'Retired',
        };
    }
}
