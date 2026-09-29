<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Service;

use Forumify\Core\Entity\User;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Entity\Squad;
use MajesticDev\CommandNet\Entity\Unit;
use MajesticDev\CommandNet\Repository\SoldierProfileRepository;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Whether the current user may manage a given unit's own settings and its children, either
 * because they have the blanket "command-net.admin.units.manage" grant, or because they hold
 * "command-net.units.manage_own" and command that unit or one of its ancestors - the same way
 * a platoon leader is expected to manage the squads beneath them without needing full admin
 * access to every unit in the community.
 */
class UnitAuthorizationChecker
{
    public function __construct(
        private readonly Security $security,
        private readonly SoldierProfileRepository $soldierProfiles,
    ) {
    }

    public function canManage(Unit $unit): bool
    {
        return $this->security->isGranted('command-net.admin.units.manage') || $this->commandsUnit($unit);
    }

    /**
     * Holds units.manage_own and commands this unit or one of its ancestors. Unlike canManage(),
     * the blanket admin.units.manage grant does not count - use this where "commands it" is the
     * question (e.g. attendance scope), not "may edit it".
     */
    public function commandsUnit(Unit $unit): bool
    {
        if (!$this->security->isGranted('command-net.units.manage_own')) {
            return false;
        }

        $soldier = $this->currentSoldier();
        if ($soldier === null) {
            return false;
        }

        for ($ancestor = $unit; $ancestor !== null; $ancestor = $ancestor->getParent()) {
            if ($ancestor->getCommander()?->getId() === $soldier->getId()) {
                return true;
            }
        }

        return false;
    }

    /**
     * A squad/team has no commander of its own - managing one is a matter of managing the
     * unit it belongs to (or one of that unit's ancestors).
     */
    public function canManageSquad(Squad $squad): bool
    {
        return $this->canManage($squad->getUnit());
    }

    public function currentSoldier(): ?SoldierProfile
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return null;
        }

        return $this->soldierProfiles->findOneBy(['user' => $user]);
    }
}
