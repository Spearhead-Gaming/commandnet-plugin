<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Service;

use MajesticDev\CommandNet\Entity\Enum\RsvpStatus;
use MajesticDev\CommandNet\Entity\Operation;

/**
 * What a new AAR starts with, taken from the patrol so the reporter has less to type.
 */
final class AarDefaults
{
    /**
     * The callsigns of the members who joined, one after another: each soldier's callsign, or their
     * name where they have none. Editable on the form - it is only a starting point.
     */
    public static function callsigns(Operation $operation): string
    {
        $names = [];
        foreach ($operation->getRsvps() as $rsvp) {
            if ($rsvp->getStatus() !== RsvpStatus::ATTENDING) {
                continue;
            }

            $soldier = $rsvp->getSoldier();
            $callsign = trim((string)$soldier->getCallsign());
            $names[] = $callsign !== '' ? $callsign : $soldier->getUser()->getDisplayName();
        }
        sort($names, SORT_NATURAL | SORT_FLAG_CASE);

        return implode(', ', $names);
    }
}
