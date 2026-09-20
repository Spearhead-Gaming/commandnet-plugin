<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Service;

use MajesticDev\CommandNet\Entity\Roster;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Entity\Unit;

/**
 * Splits a list of soldiers into the units of a roster, by the unit of their current primary
 * assignment. Every unit of the roster gets an entry, even an empty one, in the order staff put
 * the units in; a soldier in none of them is left out, and the order within a unit is kept.
 */
class RosterGrouping
{
    /**
     * @param array<SoldierProfile> $soldiers already in the order they should be shown
     * @return array<int, array{unit: Unit, soldiers: array<SoldierProfile>}>
     */
    public function group(Roster $roster, array $soldiers): array
    {
        $groups = [];
        foreach ($roster->getUnits() as $unit) {
            $groups[$unit->getId()] = ['unit' => $unit, 'soldiers' => []];
        }

        foreach ($soldiers as $soldier) {
            $unitId = $soldier->getPrimaryAssignment()?->getUnit()->getId();
            if ($unitId !== null && isset($groups[$unitId])) {
                $groups[$unitId]['soldiers'][] = $soldier;
            }
        }

        return array_values($groups);
    }
}
