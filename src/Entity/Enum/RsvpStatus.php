<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Entity\Enum;

enum RsvpStatus: string
{
    case NO_RESPONSE = 'no_response';
    case ATTENDING = 'attending';
    case MAYBE = 'maybe';
    case DECLINED = 'declined';

    public function label(): string
    {
        return match ($this) {
            self::NO_RESPONSE => 'No Response',
            self::ATTENDING => 'Attending',
            self::MAYBE => 'Maybe',
            self::DECLINED => 'Declined',
        };
    }
}
