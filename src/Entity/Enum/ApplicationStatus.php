<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Entity\Enum;

enum ApplicationStatus: string
{
    case PENDING = 'pending';
    case ACCEPTED = 'accepted';
    case DECLINED = 'declined';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::ACCEPTED => 'Accepted',
            self::DECLINED => 'Declined',
        };
    }
}
