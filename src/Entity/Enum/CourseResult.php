<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Entity\Enum;

enum CourseResult: string
{
    case PASSED = 'passed';
    case FAILED = 'failed';
    case NO_SHOW = 'no_show';
    case EXCUSED = 'excused';

    public function label(): string
    {
        return match ($this) {
            self::PASSED => 'Passed',
            self::FAILED => 'Failed',
            self::NO_SHOW => 'No-show',
            self::EXCUSED => 'Excused',
        };
    }
}
