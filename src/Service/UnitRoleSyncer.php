<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Service;

use Forumify\Core\Repository\UserRepository;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Entity\Unit;
use MajesticDev\CommandNet\Repository\UnitRepository;

/**
 * Grants a soldier the forumify Role tied to their primary unit and revokes any other
 * unit's role - a transfer, discharge, or assignment deletion is just a resync. The Discord
 * plugin's own admin-configured Role->Discord-Role mapping takes it from there, so this
 * never has to know Discord exists.
 */
class UnitRoleSyncer
{
    public function __construct(
        private readonly UnitRepository $unitRepository,
        private readonly UserRepository $userRepository,
    ) {
    }

    public function sync(SoldierProfile $profile): void
    {
        $user = $profile->getUser();
        $currentUnit = $profile->getPrimaryAssignment()?->getUnit();
        $keepRoleId = $currentUnit?->getRole()?->getId();

        $changed = false;
        foreach ($this->unitRepository->findBy([]) as $unit) {
            $role = $unit->getRole();
            if ($role === null || $role->getId() === $keepRoleId) {
                continue;
            }

            if ($user->getRoleEntities()->removeElement($role)) {
                $changed = true;
            }
        }

        if ($currentUnit instanceof Unit && $currentUnit->getRole() !== null && !$user->getRoleEntities()->contains($currentUnit->getRole())) {
            $user->addRoleEntity($currentUnit->getRole());
            $changed = true;
        }

        if ($changed) {
            $this->userRepository->save($user);
        }
    }
}
