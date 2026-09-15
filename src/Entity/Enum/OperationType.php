<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Entity\Enum;

enum OperationType: string
{
    case OPERATION = 'operation';
    case TRAINING = 'training';
    case MEETING = 'meeting';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::OPERATION => 'Operation',
            self::TRAINING => 'Training',
            self::MEETING => 'Meeting',
            self::OTHER => 'Other',
        };
    }
}
