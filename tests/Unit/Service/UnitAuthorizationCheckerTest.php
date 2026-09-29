<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Tests\Unit\Service;

use Forumify\Core\Entity\User;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Entity\Unit;
use MajesticDev\CommandNet\Repository\SoldierProfileRepository;
use MajesticDev\CommandNet\Service\UnitAuthorizationChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

class UnitAuthorizationCheckerTest extends TestCase
{
    public function testCommanderIsMatchedByIdNotObjectIdentity(): void
    {
        $unit = new Unit();
        $unit->setCommander($this->soldier(7));

        // A second, separately hydrated instance of the same row.
        $this->assertTrue($this->checker($this->soldier(7))->canManage($unit));
    }

    public function testDifferentSoldierIsDenied(): void
    {
        $unit = new Unit();
        $unit->setCommander($this->soldier(7));

        $this->assertFalse($this->checker($this->soldier(8))->canManage($unit));
    }

    public function testUnitWithNoCommanderIsDenied(): void
    {
        $this->assertFalse($this->checker($this->soldier(7))->canManage(new Unit()));
    }

    public function testCommanderOfAnAncestorMayManageTheChild(): void
    {
        $parent = new Unit();
        $parent->setCommander($this->soldier(7));
        $child = new Unit();
        $child->setParent($parent);

        $this->assertTrue($this->checker($this->soldier(7))->canManage($child));
    }

    private function soldier(int $id): SoldierProfile
    {
        $soldier = $this->createStub(SoldierProfile::class);
        $soldier->method('getId')->willReturn($id);

        return $soldier;
    }

    private function checker(SoldierProfile $current): UnitAuthorizationChecker
    {
        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturnCallback(
            static fn (string $permission): bool => $permission === 'command-net.units.manage_own',
        );
        $security->method('getUser')->willReturn($this->createStub(User::class));

        $soldiers = $this->createStub(SoldierProfileRepository::class);
        $soldiers->method('findOneBy')->willReturn($current);

        return new UnitAuthorizationChecker($security, $soldiers);
    }
}
