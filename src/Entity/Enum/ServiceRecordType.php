<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Entity\Enum;

enum ServiceRecordType: string
{
    case ENLISTMENT = 'enlistment';
    case PROMOTION = 'promotion';
    case DEMOTION = 'demotion';
    case ASSIGNMENT = 'assignment';
    case AWARD = 'award';
    case QUALIFICATION = 'qualification';
    case COMBAT = 'combat';
    case DISCIPLINARY = 'disciplinary';
    case AWOL = 'awol';
    case NOTE = 'note';

    public function label(): string
    {
        return match ($this) {
            self::ENLISTMENT => 'Enlistment',
            self::PROMOTION => 'Promotion',
            self::DEMOTION => 'Demotion',
            self::ASSIGNMENT => 'Assignment',
            self::AWARD => 'Award',
            self::QUALIFICATION => 'Qualification',
            self::COMBAT => 'Combat Record',
            self::DISCIPLINARY => 'Disciplinary',
            self::AWOL => 'AWOL',
            self::NOTE => 'Note',
        };
    }
}
