<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Service;

use DateTime;

/**
 * A snapshot of one soldier's attendance record, computed on the fly from their RSVP
 * history rather than stored - there's no need to keep this in sync with anything.
 */
final class AttendanceStats
{
    public function __construct(
        public readonly int $attended,
        public readonly int $noShows,
        public readonly ?float $noShowRate,
        public readonly ?DateTime $lastAttended,
        public readonly int $currentStreak,
    ) {
    }

    public function hasHistory(): bool
    {
        return $this->attended + $this->noShows > 0;
    }
}