<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Service;

use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Entity\Unit;
use MajesticDev\CommandNet\Repository\SoldierProfileRepository;
use MajesticDev\CommandNet\Repository\UnitRepository;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Whose attendance the current user may review, and whose they may correct.
 *
 * Community leadership (command-net.admin.attendance.view / .manage) covers everyone. A unit
 * commander (command-net.units.manage_own, by UnitAuthorizationChecker's own commander-or-ancestor
 * rule) covers soldiers whose primary assignment is in their unit tree, and may correct them too -
 * but never themselves. A member may read their own history (command-net.attendance.view_own),
 * never change it.
 */
class AttendanceScope
{
    public function __construct(
        private readonly Security $security,
        private readonly UnitAuthorizationChecker $unitAuthorization,
        private readonly UnitRepository $unitRepository,
        private readonly SoldierProfileRepository $soldierProfileRepository,
    ) {
    }

    public function canReviewEveryone(): bool
    {
        return $this->security->isGranted('command-net.admin.attendance.view');
    }

    /**
     * Units whose soldiers the user reviews as leadership.
     * ponytail: canManage() looks up the current soldier once per unit; cache it if unit counts grow large.
     *
     * @return array<Unit>
     */
    public function units(): array
    {
        $units = $this->unitRepository->findBy([], ['name' => 'ASC']);
        if ($this->canReviewEveryone()) {
            return $units;
        }

        return array_values(array_filter($units, fn (Unit $unit): bool => $this->unitAuthorization->canManage($unit)));
    }

    /**
     * Leadership access: everyone, or at least one unit tree.
     */
    public function hasLeadershipAccess(): bool
    {
        return $this->canReviewEveryone() || $this->units() !== [];
    }

    /**
     * Anyone who may open the review pages at all - leadership, or a member reading their own.
     */
    public function mayUseReview(): bool
    {
        return $this->security->isGranted('command-net.attendance.view_own') || $this->hasLeadershipAccess();
    }

    /**
     * Active personnel in the user's leadership scope.
     *
     * @return array<SoldierProfile>
     */
    public function soldiers(): array
    {
        $roster = $this->soldierProfileRepository->findRoster();
        if ($this->canReviewEveryone()) {
            return $roster;
        }

        $unitIds = array_map(static fn (Unit $unit): int => $unit->getId(), $this->units());

        return array_values(array_filter(
            $roster,
            static fn (SoldierProfile $soldier): bool => in_array($soldier->getPrimaryAssignment()?->getUnit()->getId(), $unitIds, true),
        ));
    }

    public function canReview(SoldierProfile $soldier): bool
    {
        if ($this->canReviewEveryone() || $this->inCommandedUnit($soldier)) {
            return true;
        }

        return $this->isSelf($soldier) && $this->security->isGranted('command-net.attendance.view_own');
    }

    public function canCorrect(SoldierProfile $soldier): bool
    {
        if ($this->security->isGranted('command-net.admin.attendance.manage')) {
            return true;
        }

        return !$this->isSelf($soldier) && $this->inCommandedUnit($soldier);
    }

    private function inCommandedUnit(SoldierProfile $soldier): bool
    {
        $unit = $soldier->getPrimaryAssignment()?->getUnit();

        return $unit !== null && $this->unitAuthorization->canManage($unit);
    }

    private function isSelf(SoldierProfile $soldier): bool
    {
        $current = $this->unitAuthorization->currentSoldier();

        return $current !== null && $current->getId() === $soldier->getId();
    }
}
