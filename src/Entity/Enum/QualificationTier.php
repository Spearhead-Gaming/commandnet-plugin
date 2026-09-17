<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Entity\Enum;

enum QualificationTier: string
{
    case TIER_1 = 'tier_1';
    case TIER_2 = 'tier_2';
    case TIER_3 = 'tier_3';

    public function label(): string
    {
        return match ($this) {
            self::TIER_1 => 'Tier I - Core Qualifications',
            self::TIER_2 => 'Tier II - Advanced & Specialty Qualifications',
            self::TIER_3 => 'Tier III - Staff, Combined Arms & Platoon Qualifications',
        };
    }
}
