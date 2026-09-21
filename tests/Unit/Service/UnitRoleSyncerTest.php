<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Tests\Unit\Service;

use DateTime;
use Forumify\Core\Entity\Role;
use Forumify\Core\Entity\User;
use Forumify\Core\Repository\UserRepository;
use MajesticDev\CommandNet\Entity\Assignment;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Entity\Unit;
use MajesticDev\CommandNet\Repository\UnitRepository;
use MajesticDev\CommandNet\Service\UnitRoleSyncer;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class UnitRoleSyncerTest extends TestCase
{
    public function testGrantsThePrimaryUnitsRoleAndRevokesOtherUnitsRoles(): void
    {
        $alphaRole = $this->role(1);
        $bravoRole = $this->role(2);
        $alpha = $this->unit($alphaRole);
        $bravo = $this->unit($bravoRole);
        $user = new User();
        $user->addRoleEntity($alphaRole);
        $soldier = new SoldierProfile($user);
        $soldier->addAssignment(new Assignment($soldier, $bravo));

        $this->syncer([$alpha, $bravo])->sync($soldier);

        $this->assertTrue($user->getRoleEntities()->contains($bravoRole));
        $this->assertFalse($user->getRoleEntities()->contains($alphaRole));
    }

    /**
     * The roster controller ends the current posting and adds the new one before syncing. The
     * new assignment has to be on the soldier by then, or the sync sees no primary posting at all
     * and leaves the soldier without the new unit's role.
     */
    public function testTransferMovesTheRoleFromTheOldUnitToTheNewOne(): void
    {
        $alphaRole = $this->role(1);
        $bravoRole = $this->role(2);
        $alpha = $this->unit($alphaRole);
        $bravo = $this->unit($bravoRole);
        $user = new User();
        $user->addRoleEntity($alphaRole);
        $soldier = new SoldierProfile($user);
        $current = new Assignment($soldier, $alpha);
        $soldier->addAssignment($current);

        $current->setEndDate(new DateTime());
        $soldier->addAssignment(new Assignment($soldier, $bravo));

        $this->syncer([$alpha, $bravo])->sync($soldier);

        $this->assertTrue($user->getRoleEntities()->contains($bravoRole));
        $this->assertFalse($user->getRoleEntities()->contains($alphaRole));
    }

    public function testNoOpenPrimaryAssignmentRevokesEveryUnitRole(): void
    {
        $role = $this->role(1);
        $unit = $this->unit($role);
        $user = new User();
        $user->addRoleEntity($role);
        $soldier = new SoldierProfile($user);
        $assignment = new Assignment($soldier, $unit);
        $assignment->setEndDate(new DateTime());
        $soldier->addAssignment($assignment);

        $this->syncer([$unit])->sync($soldier);

        $this->assertFalse($user->getRoleEntities()->contains($role));
    }

    public function testUnitsWithoutARoleAreIgnoredAndNothingIsSavedWhenUnchanged(): void
    {
        $roleless = $this->unit(null);
        $soldier = new SoldierProfile(new User());
        $soldier->addAssignment(new Assignment($soldier, $roleless));

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->expects($this->never())->method('save');

        $this->syncer([$roleless], $userRepository)->sync($soldier);
    }

    /**
     * @param array<Unit> $units
     */
    private function syncer(array $units, ?UserRepository $userRepository = null): UnitRoleSyncer
    {
        $unitRepository = $this->createMock(UnitRepository::class);
        $unitRepository->method('findBy')->willReturn($units);

        return new UnitRoleSyncer($unitRepository, $userRepository ?? $this->createMock(UserRepository::class));
    }

    private function unit(?Role $role): Unit
    {
        $unit = new Unit();
        $unit->setRole($role);

        return $unit;
    }

    private function role(int $id): Role
    {
        $role = new Role();
        (new ReflectionProperty($role, 'id'))->setValue($role, $id);

        return $role;
    }
}
