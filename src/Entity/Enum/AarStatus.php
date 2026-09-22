<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Entity\Enum;

/**
 * Where an event stands on its after-action report. Never stored: EventRules works it out from
 * the event's type, status, end time and whether an AAR exists.
 */
enum AarStatus: string
{
    case NOT_REQUIRED = 'not_required';
    case NOT_YET_DUE = 'not_yet_due';
    case DUE = 'due';
    case OVERDUE = 'overdue';
    case FILED = 'filed';

    public function label(): string
    {
        return match ($this) {
            self::NOT_REQUIRED => 'No AAR required',
            self::NOT_YET_DUE => 'AAR due after the patrol',
            self::DUE => 'AAR due',
            self::OVERDUE => 'AAR overdue',
            self::FILED => 'AAR filed',
        };
    }
}
