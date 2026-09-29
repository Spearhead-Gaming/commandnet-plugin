<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Tests\Unit\Service;

use Forumify\Core\Entity\User;
use MajesticDev\CommandNet\Entity\Assignment;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Entity\Unit;
use MajesticDev\CommandNet\Repository\SoldierProfileRepository;
use MajesticDev\CommandNet\Repository\UnitRepository;
use MajesticDev\CommandNet\Service\AttendanceScope;
use MajesticDev\CommandNet\Service\UnitAuthorizationChecker;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Bundle\SecurityBundle\Security;

class AttendanceScopeTest extends TestCase
{
    private Unit $alpha;
    private Unit $bravo;
    private SoldierProfile $inAlpha;
    private SoldierProfile $inBravo;
    private SoldierProfile $me;

    protected function setUp(): void
    {
        $this->alpha = $this->unit(1, 'Alpha');
        $this->bravo = $this->unit(2, 'Bravo');
        $this->inAlpha = $this->soldier(10, $this->alpha);
        $this->inBravo = $this->soldier(11, $this->bravo);
        // The current user's own file, which sits in Alpha - the unit they command below.
        $this->me = $this->soldier(12, $this->alpha);
    }

    public function testCommunityLeadershipSeesAndCorrectsEveryone(): void
    {
        $scope = $this->scope(['command-net.admin.attendance.view', 'command-net.admin.attendance.manage']);

        $this->assertTrue($scope->hasLeadershipAccess());
        $this->assertSame([$this->alpha, $this->bravo], $scope->units());
        $this->assertSame([$this->inAlpha, $this->inBravo, $this->me], $scope->soldiers());
        $this->assertTrue($scope->canReview($this->inBravo));
        $this->assertTrue($scope->canCorrect($this->inBravo));
        $this->assertTrue($scope->canCorrect($this->me), 'Community-wide managers are not held back from their own record.');
    }

    public function testViewOnlyLeadershipReviewsButCannotCorrect(): void
    {
        $scope = $this->scope(['command-net.admin.attendance.view']);

        $this->assertTrue($scope->canReview($this->inBravo));
        $this->assertFalse($scope->canCorrect($this->inBravo));
    }

    public function testUnitCommanderIsScopedToTheirTreeAndCannotCorrectThemselves(): void
    {
        $scope = $this->scope(['command-net.units.manage_own'], commanded: [$this->alpha]);

        $this->assertTrue($scope->hasLeadershipAccess());
        $this->assertSame([$this->alpha], $scope->units());
        $this->assertSame([$this->inAlpha, $this->me], $scope->soldiers(), 'Only soldiers whose primary unit is in the tree.');
        $this->assertTrue($scope->canReview($this->inAlpha));
        $this->assertTrue($scope->canCorrect($this->inAlpha));
        $this->assertFalse($scope->canReview($this->inBravo));
        $this->assertFalse($scope->canCorrect($this->inBravo));
        $this->assertFalse($scope->canCorrect($this->me), 'A commander cannot edit their own attendance.');
    }

    public function testCommanderOfNothingHasNoLeadershipAccess(): void
    {
        $scope = $this->scope(['command-net.units.manage_own'], commanded: []);

        $this->assertFalse($scope->hasLeadershipAccess());
    }

    public function testMemberReadsOnlyTheirOwnHistoryAndNeverChangesIt(): void
    {
        $scope = $this->scope(['command-net.attendance.view_own']);

        $this->assertFalse($scope->hasLeadershipAccess());
        $this->assertTrue($scope->mayUseReview());
        $this->assertTrue($scope->canReview($this->me));
        $this->assertFalse($scope->canReview($this->inAlpha));
        $this->assertFalse($scope->canCorrect($this->me));
    }

    public function testNoPermissionsMeansNoAccess(): void
    {
        $scope = $this->scope([]);

        $this->assertFalse($scope->mayUseReview());
        $this->assertFalse($scope->canReview($this->me));
    }

    /**
     * @param array<string> $granted
     * @param array<Unit> $commanded units the user commands
     */
    private function scope(array $granted, array $commanded = []): AttendanceScope
    {
        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturnCallback(static fn (string $permission): bool => in_array($permission, $granted, true));
        $authorization = $this->createStub(UnitAuthorizationChecker::class);
        $authorization->method('canManage')->willReturnCallback(static fn (Unit $unit): bool => in_array($unit, $commanded, true));
        $authorization->method('currentSoldier')->willReturn($this->me);
        $units = $this->createStub(UnitRepository::class);
        $units->method('findBy')->willReturn([$this->alpha, $this->bravo]);
        $soldiers = $this->createStub(SoldierProfileRepository::class);
        $soldiers->method('findRoster')->willReturn([$this->inAlpha, $this->inBravo, $this->me]);

        return new AttendanceScope($security, $authorization, $units, $soldiers);
    }

    private function unit(int $id, string $name): Unit
    {
        $unit = new Unit();
        $unit->setName($name);
        (new ReflectionProperty($unit, 'id'))->setValue($unit, $id);

        return $unit;
    }

    private function soldier(int $id, Unit $unit): SoldierProfile
    {
        $soldier = new SoldierProfile($this->createStub(User::class));
        (new ReflectionProperty($soldier, 'id'))->setValue($soldier, $id);
        $assignment = new Assignment($soldier, $unit);
        $assignment->setIsPrimary(true);
        $soldier->addAssignment($assignment);

        return $soldier;
    }
}
