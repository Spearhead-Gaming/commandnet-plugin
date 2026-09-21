<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Tests\Unit\Service;

use Forumify\Core\Entity\Role;
use Forumify\Core\Entity\User;
use Forumify\Core\Repository\UserRepository;
use MajesticDev\CommandNet\Entity\Rank;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Repository\RankRepository;
use MajesticDev\CommandNet\Service\RankRoleSyncer;
use PHPUnit\Framework\TestCase;

class RankRoleSyncerTest extends TestCase
{
    public function testGrantsTheCurrentRanksRoleAndRevokesOtherRanksRoles(): void
    {
        $privateRole = new Role();
        $sergeantRole = new Role();
        $unrelatedRole = new Role();
        $private = $this->rank($privateRole);
        $sergeant = $this->rank($sergeantRole);
        $noRole = $this->rank(null);

        $user = new User();
        $user->addRoleEntity($privateRole);
        $user->addRoleEntity($unrelatedRole);
        $soldier = new SoldierProfile($user);
        $soldier->setRank($sergeant);

        $this->syncer([$private, $sergeant, $noRole])->sync($soldier);

        $roles = $user->getRoleEntities();
        $this->assertTrue($roles->contains($sergeantRole));
        $this->assertFalse($roles->contains($privateRole));
        $this->assertTrue($roles->contains($unrelatedRole), 'Roles that belong to no rank are never touched.');
    }

    public function testClearedRankRevokesEveryRankRole(): void
    {
        $role = new Role();
        $user = new User();
        $user->addRoleEntity($role);
        $soldier = new SoldierProfile($user);

        $this->syncer([$this->rank($role)])->sync($soldier);

        $this->assertFalse($user->getRoleEntities()->contains($role));
    }

    public function testDoesNotSaveWhenNothingChanged(): void
    {
        $role = new Role();
        $rank = $this->rank($role);
        $user = new User();
        $user->addRoleEntity($role);
        $soldier = new SoldierProfile($user);
        $soldier->setRank($rank);

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->expects($this->never())->method('save');

        $this->syncer([$rank], $userRepository)->sync($soldier);
    }

    /**
     * @param Rank[] $ranks
     */
    private function syncer(array $ranks, ?UserRepository $userRepository = null): RankRoleSyncer
    {
        $rankRepository = $this->createMock(RankRepository::class);
        $rankRepository->method('findAll')->willReturn($ranks);

        return new RankRoleSyncer($rankRepository, $userRepository ?? $this->createMock(UserRepository::class));
    }

    private function rank(?Role $role): Rank
    {
        $rank = new Rank();
        $rank->setRole($role);

        return $rank;
    }
}
