<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Entity\Enum;

enum DischargeKind: string
{
    case GENERAL = 'general';
    case HONORABLE = 'honorable';
    case RETIREMENT = 'retirement';
    case DISHONORABLE = 'dishonorable';

    public function label(): string
    {
        return match ($this) {
            self::GENERAL => 'General Discharge',
            self::HONORABLE => 'Honorable Discharge',
            self::RETIREMENT => 'Retirement',
            self::DISHONORABLE => 'Dishonorable Discharge',
        };
    }

    /**
     * Retirement is the only one that isn't a discharge.
     */
    public function status(): SoldierStatus
    {
        return $this === self::RETIREMENT ? SoldierStatus::RETIRED : SoldierStatus::DISCHARGED;
    }
}
