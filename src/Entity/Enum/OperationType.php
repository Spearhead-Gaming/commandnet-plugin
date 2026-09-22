<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Entity\Enum;

enum OperationType: string
{
    case OPERATION = 'operation';
    case TRAINING = 'training';
    case MEETING = 'meeting';
    case OTHER = 'other';
    case PATROL = 'patrol';
    case FUN_DAY = 'fun_day';

    public function label(): string
    {
        return match ($this) {
            self::OPERATION => 'Operation',
            self::TRAINING => 'Training',
            self::MEETING => 'Meeting',
            self::OTHER => 'Other',
            self::PATROL => 'Patrol',
            self::FUN_DAY => 'Fun-Day',
        };
    }
}
