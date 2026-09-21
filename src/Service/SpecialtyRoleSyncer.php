<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Service;

use Forumify\Core\Repository\UserRepository;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Repository\SpecialtyRepository;

/**
 * Grants a soldier the forumify Role tied to their specialty and revokes every other
 * specialty's role - same approach as RankRoleSyncer and UnitRoleSyncer.
 */
class SpecialtyRoleSyncer
{
    public function __construct(
        private readonly SpecialtyRepository $specialtyRepository,
        private readonly UserRepository $userRepository,
    ) {
    }

    /**
     * With $revokeAll, no specialty role is kept - for a soldier who is leaving.
     */
    public function sync(SoldierProfile $profile, bool $revokeAll = false): void
    {
        $user = $profile->getUser();
        $keepRole = $revokeAll ? null : $profile->getSpecialty()?->getRole();

        $changed = false;
        foreach ($this->specialtyRepository->findAll() as $specialty) {
            $role = $specialty->getRole();
            if ($role === null || $role === $keepRole) {
                continue;
            }

            if ($user->getRoleEntities()->removeElement($role)) {
                $changed = true;
            }
        }

        if ($keepRole !== null && !$user->getRoleEntities()->contains($keepRole)) {
            $user->addRoleEntity($keepRole);
            $changed = true;
        }

        if ($changed) {
            $this->userRepository->save($user);
        }
    }
}
