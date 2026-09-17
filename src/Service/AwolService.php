<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Service;

use Forumify\Core\Repository\RoleRepository;
use Forumify\Core\Repository\UserRepository;
use MajesticDev\CommandNet\Entity\Enum\SoldierStatus;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Repository\SoldierProfileRepository;

/**
 * Flags/clears AWOL based on attendance rather than a scheduled report-in check: a
 * configurable number of consecutive missed operations (AttendanceStats::$currentMissStreak)
 * flags a soldier AWOL, and their next attended operation clears it. Discord visibility is
 * the same zero-Discord-code trick as UnitRoleSyncer - grant/revoke a plain forumify Role
 * and let the Discord plugin's own Role<->Discord-Role mapping do the rest.
 */
class AwolService
{
    public function __construct(
        private readonly AwolSettings $settings,
        private readonly AttendanceCalculator $attendanceCalculator,
        private readonly RoleRepository $roleRepository,
        private readonly SoldierProfileRepository $soldierProfileRepository,
        private readonly UserRepository $userRepository,
    ) {
    }

    public function checkAfterAttendanceChange(SoldierProfile $profile): void
    {
        $settings = $this->settings->all();
        if (!$settings['enabled']) {
            return;
        }

        $stats = $this->attendanceCalculator->calculate($profile);

        if ($stats->currentMissStreak >= (int)$settings['missThreshold'] && $profile->getStatus() === SoldierStatus::ACTIVE) {
            $profile->setStatus(SoldierStatus::AWOL);
            $this->soldierProfileRepository->save($profile);
            $this->syncRole($profile, $settings['role'], grant: true);
            return;
        }

        if ($stats->currentMissStreak === 0 && $profile->getStatus() === SoldierStatus::AWOL) {
            $profile->setStatus(SoldierStatus::ACTIVE);
            $this->soldierProfileRepository->save($profile);
            $this->syncRole($profile, $settings['role'], grant: false);
        }
    }

    private function syncRole(SoldierProfile $profile, ?int $roleId, bool $grant): void
    {
        if ($roleId === null) {
            return;
        }

        $role = $this->roleRepository->find($roleId);
        if ($role === null) {
            return;
        }

        $user = $profile->getUser();
        $roles = $user->getRoleEntities();
        if ($grant) {
            $changed = !$roles->contains($role);
            if ($changed) {
                $user->addRoleEntity($role);
            }
        } else {
            $changed = $roles->removeElement($role);
        }

        if ($changed) {
            $this->userRepository->save($user);
        }
    }
}
