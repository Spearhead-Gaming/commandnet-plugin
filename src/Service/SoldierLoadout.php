<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Service;

use MajesticDev\CommandNet\Entity\Equipment;
use MajesticDev\CommandNet\Entity\SoldierProfile;

/**
 * What a soldier is cleared to use: the weapons listed on the position of their primary
 * assignment, and the vehicles listed on that assignment unit. Nothing is stored per soldier,
 * so a change to a position or unit applies to everyone holding it.
 */
class SoldierLoadout
{
    /**
     * @return array{primaryWeapons: array<Equipment>, secondaryWeapons: array<Equipment>, vehicles: array<Equipment>}
     */
    public function forSoldier(SoldierProfile $soldier): array
    {
        $assignment = $soldier->getPrimaryAssignment();
        $position = $assignment?->getPosition();

        return [
            'primaryWeapons' => $position !== null ? array_values($position->getPrimaryWeapons()->toArray()) : [],
            'secondaryWeapons' => $position !== null ? array_values($position->getSecondaryWeapons()->toArray()) : [],
            'vehicles' => $assignment !== null ? array_values($assignment->getUnit()->getVehicles()->toArray()) : [],
        ];
    }
}
