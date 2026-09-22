<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Tests\Unit\Service;

use Doctrine\Common\Collections\ArrayCollection;
use Forumify\Core\Entity\Role;
use Forumify\Core\Entity\User;
use MajesticDev\CommandNet\Service\RawPermissionChecker;
use PHPUnit\Framework\TestCase;

class RawPermissionCheckerTest extends TestCase
{
    public function testGrantedWhenAnyRoleCarriesThePermission(): void
    {
        $other = new Role();
        $other->setPermissions(['some.other.permission']);
        $granting = new Role();
        $granting->setPermissions(['command-net.patrols.create']);

        $user = $this->createStub(User::class);
        $user->method('getRoleEntities')->willReturn(new ArrayCollection([$other, $granting]));

        $this->assertTrue((new RawPermissionChecker())->isGranted($user, 'command-net.patrols.create'));
    }

    public function testNotGrantedWhenNoRoleCarriesIt(): void
    {
        $role = new Role();
        $role->setPermissions(['some.other.permission']);

        $user = $this->createStub(User::class);
        $user->method('getRoleEntities')->willReturn(new ArrayCollection([$role]));

        $this->assertFalse((new RawPermissionChecker())->isGranted($user, 'command-net.patrols.create'));
    }

    public function testNotGrantedWithNoRoles(): void
    {
        $user = $this->createStub(User::class);
        $user->method('getRoleEntities')->willReturn(new ArrayCollection([]));

        $this->assertFalse((new RawPermissionChecker())->isGranted($user, 'command-net.patrols.create'));
    }
}
