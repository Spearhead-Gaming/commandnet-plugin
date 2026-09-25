<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Tests\Unit\Service;

use Doctrine\Common\Collections\ArrayCollection;
use Forumify\Core\Entity\Role;
use Forumify\Core\Entity\User;
use Forumify\Core\Repository\RoleRepository;
use MajesticDev\CommandNet\Service\RawPermissionChecker;
use PHPUnit\Framework\TestCase;

class RawPermissionCheckerTest extends TestCase
{
    private const string PERMISSION = 'command-net.patrols.create';

    public function testGrantedWhenAnyAttachedRoleCarriesThePermission(): void
    {
        $user = $this->user($this->role(['some.other.permission']), $this->role([self::PERMISSION]));

        $this->assertTrue($this->checker()->isGranted($user, self::PERMISSION));
    }

    public function testNotGrantedWhenNoRoleCarriesIt(): void
    {
        $user = $this->user($this->role(['some.other.permission']));

        $this->assertFalse($this->checker()->isGranted($user, self::PERMISSION));
    }

    public function testNotGrantedWithNoRoles(): void
    {
        $this->assertFalse($this->checker()->isGranted($this->user(), self::PERMISSION));
    }

    public function testSuperAdminIsGrantedEverythingWithoutListingIt(): void
    {
        $user = $this->user($this->role([], 'ROLE_SUPER_ADMIN'));

        $this->assertTrue($this->checker()->isGranted($user, self::PERMISSION));
        $this->assertTrue($this->checker()->isGranted($user, 'command-net.admin.anything.manage'));
    }

    public function testTheBuiltInUserRoleGrantsToAccountsWithNoRolesAttached(): void
    {
        $checker = $this->checker($this->role([self::PERMISSION]));

        $this->assertTrue($checker->isGranted($this->user(), self::PERMISSION), 'e.g. a member imported from Discord who never logged in');
        $this->assertFalse($checker->isGranted($this->user(), 'command-net.operations.rsvp'), 'only what the role lists');
        $this->assertTrue($checker->grantedByUserRole(self::PERMISSION));
    }

    public function testNothingIsGrantedByAMissingUserRole(): void
    {
        $this->assertFalse($this->checker(null)->grantedByUserRole(self::PERMISSION));
    }

    /**
     * @param array<string> $permissions
     */
    private function role(array $permissions, string $name = 'ROLE_SOMETHING'): Role
    {
        $role = $this->createStub(Role::class);
        $role->method('getPermissions')->willReturn($permissions);
        $role->method('getRoleName')->willReturn($name);

        return $role;
    }

    private function user(Role ...$roles): User
    {
        $user = $this->createStub(User::class);
        $user->method('getRoleEntities')->willReturn(new ArrayCollection($roles));

        return $user;
    }

    private function checker(?Role $userRole = null): RawPermissionChecker
    {
        $repository = $this->createStub(RoleRepository::class);
        $repository->method('findOneBy')->willReturn($userRole);

        return new RawPermissionChecker($repository);
    }
}
