<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Service;

use Forumify\Core\Repository\UserRepository;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Repository\RankRepository;

/**
 * Grants a soldier the forumify Role tied to their current rank and revokes every other
 * rank's role, so a promotion, demotion or cleared rank is just a resync. Same approach as
 * UnitRoleSyncer: the Discord plugin's own Role -> Discord-Role mapping does the rest.
 */
class RankRoleSyncer
{
    public function __construct(
        private readonly RankRepository $rankRepository,
        private readonly UserRepository $userRepository,
    ) {
    }

    /**
     * With $revokeAll, no rank role is kept - for a soldier leaving the unit, who keeps their
     * rank on file but shouldn't keep the role that goes with it.
     */
    public function sync(SoldierProfile $profile, bool $revokeAll = false): void
    {
        $user = $profile->getUser();
        $keepRole = $revokeAll ? null : $profile->getRank()?->getRole();

        $changed = false;
        foreach ($this->rankRepository->findAll() as $rank) {
            $role = $rank->getRole();
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
