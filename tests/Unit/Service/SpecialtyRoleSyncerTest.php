<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Tests\Unit\Service;

use Forumify\Core\Entity\Role;
use Forumify\Core\Entity\User;
use Forumify\Core\Repository\UserRepository;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Entity\Specialty;
use MajesticDev\CommandNet\Repository\SpecialtyRepository;
use MajesticDev\CommandNet\Service\SpecialtyRoleSyncer;
use PHPUnit\Framework\TestCase;

class SpecialtyRoleSyncerTest extends TestCase
{
    public function testGrantsTheCurrentSpecialtysRoleAndRevokesOthers(): void
    {
        $medicRole = new Role();
        $radioRole = new Role();
        $unrelatedRole = new Role();
        $medic = $this->specialty($medicRole);
        $radio = $this->specialty($radioRole);

        $user = new User();
        $user->addRoleEntity($medicRole);
        $user->addRoleEntity($unrelatedRole);
        $soldier = new SoldierProfile($user);
        $soldier->setSpecialty($radio);

        $this->syncer([$medic, $radio])->sync($soldier);

        $roles = $user->getRoleEntities();
        $this->assertTrue($roles->contains($radioRole));
        $this->assertFalse($roles->contains($medicRole));
        $this->assertTrue($roles->contains($unrelatedRole), 'Roles that belong to no specialty are never touched.');
    }

    public function testClearedSpecialtyRevokesItsRole(): void
    {
        $role = new Role();
        $user = new User();
        $user->addRoleEntity($role);
        $soldier = new SoldierProfile($user);

        $this->syncer([$this->specialty($role)])->sync($soldier);

        $this->assertFalse($user->getRoleEntities()->contains($role));
    }

    public function testRevokeAllRemovesTheCurrentSpecialtysRoleToo(): void
    {
        $role = new Role();
        $specialty = $this->specialty($role);
        $user = new User();
        $user->addRoleEntity($role);
        $soldier = new SoldierProfile($user);
        $soldier->setSpecialty($specialty);

        $this->syncer([$specialty])->sync($soldier, true);

        $this->assertFalse($user->getRoleEntities()->contains($role));
    }

    public function testSpecialtyWithoutARoleChangesNothingAndSavesNothing(): void
    {
        $specialty = $this->specialty(null);
        $soldier = new SoldierProfile(new User());
        $soldier->setSpecialty($specialty);

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->expects($this->never())->method('save');

        $this->syncer([$specialty], $userRepository)->sync($soldier);
    }

    /**
     * @param array<Specialty> $specialties
     */
    private function syncer(array $specialties, ?UserRepository $userRepository = null): SpecialtyRoleSyncer
    {
        $repository = $this->createMock(SpecialtyRepository::class);
        $repository->method('findAll')->willReturn($specialties);

        return new SpecialtyRoleSyncer($repository, $userRepository ?? $this->createMock(UserRepository::class));
    }

    private function specialty(?Role $role): Specialty
    {
        $specialty = new Specialty();
        $specialty->setRole($role);

        return $specialty;
    }
}
