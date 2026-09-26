<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Service;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * The date-time group at the top of the community's AAR template, "DDHHHHRMMMYY": day, hour and
 * minute, the time zone letter, month and year - for example "261900ZSEP26" for 26 September 2026
 * at 19:00 UTC. Always in UTC, so the zone letter is Z whichever timezone the site runs in.
 */
final class DtgFormatter
{
    public static function format(DateTimeInterface $moment): string
    {
        $utc = DateTimeImmutable::createFromInterface($moment)->setTimezone(new DateTimeZone('UTC'));

        return strtoupper($utc->format('dHi') . 'Z' . $utc->format('My'));
    }
}
