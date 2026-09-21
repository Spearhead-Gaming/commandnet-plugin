<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Tests\Unit\Service;

use DateTime;
use Forumify\Core\Entity\User;
use MajesticDev\CommandNet\Entity\Assignment;
use MajesticDev\CommandNet\Entity\Roster;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Entity\Unit;
use MajesticDev\CommandNet\Service\RosterGrouping;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class RosterGroupingTest extends TestCase
{
    public function testSoldiersAreGroupedByTheirPrimaryUnitInTheRosterUnitOrder(): void
    {
        $alpha = $this->unit(1, 'Alpha');
        $bravo = $this->unit(2, 'Bravo');
        $roster = $this->roster($bravo, $alpha);

        $one = $this->soldierIn($alpha);
        $two = $this->soldierIn($bravo);
        $three = $this->soldierIn($alpha);

        $groups = (new RosterGrouping())->group($roster, [$one, $two, $three]);

        $this->assertSame([$bravo, $alpha], array_column($groups, 'unit'));
        $this->assertSame([$two], $groups[0]['soldiers']);
        $this->assertSame([$one, $three], $groups[1]['soldiers'], 'The incoming order is kept within a unit.');
    }

    public function testAUnitWithNobodyInItIsStillListed(): void
    {
        $alpha = $this->unit(1, 'Alpha');
        $bravo = $this->unit(2, 'Bravo');

        $groups = (new RosterGrouping())->group($this->roster($alpha, $bravo), [$this->soldierIn($alpha)]);

        $this->assertCount(2, $groups);
        $this->assertSame([], $groups[1]['soldiers']);
    }

    public function testSoldiersOutsideTheRosterUnitsAreLeftOut(): void
    {
        $alpha = $this->unit(1, 'Alpha');
        $charlie = $this->unit(3, 'Charlie');
        $unassigned = new SoldierProfile(new User());

        $groups = (new RosterGrouping())->group(
            $this->roster($alpha),
            [$this->soldierIn($charlie), $unassigned],
        );

        $this->assertSame([], $groups[0]['soldiers']);
    }

    public function testOnlyACurrentPrimaryAssignmentCounts(): void
    {
        $alpha = $this->unit(1, 'Alpha');
        $bravo = $this->unit(2, 'Bravo');

        $ended = new SoldierProfile(new User());
        $endedAssignment = new Assignment($ended, $alpha);
        $endedAssignment->setEndDate(new DateTime('-1 day'));
        $ended->addAssignment($endedAssignment);

        $secondary = new SoldierProfile(new User());
        $secondaryAssignment = new Assignment($secondary, $alpha);
        $secondaryAssignment->setIsPrimary(false);
        $secondary->addAssignment($secondaryAssignment);

        $transferred = $this->soldierIn($alpha);
        $transferred->getPrimaryAssignment()?->setEndDate(new DateTime('-1 day'));
        $transferred->addAssignment(new Assignment($transferred, $bravo));

        $groups = (new RosterGrouping())->group($this->roster($alpha, $bravo), [$ended, $secondary, $transferred]);

        $this->assertSame([], $groups[0]['soldiers']);
        $this->assertSame([$transferred], $groups[1]['soldiers']);
    }

    public function testARosterWithNoUnitsHasNoGroups(): void
    {
        $this->assertSame([], (new RosterGrouping())->group(new Roster(), [$this->soldierIn($this->unit(1, 'Alpha'))]));
    }

    private function unit(int $id, string $name): Unit
    {
        $unit = new Unit();
        $unit->setName($name);
        (new ReflectionProperty($unit, 'id'))->setValue($unit, $id);

        return $unit;
    }

    private function roster(Unit ...$units): Roster
    {
        $roster = new Roster();
        foreach ($units as $unit) {
            $roster->addUnit($unit);
        }

        return $roster;
    }

    private function soldierIn(Unit $unit): SoldierProfile
    {
        $soldier = new SoldierProfile(new User());
        $soldier->addAssignment(new Assignment($soldier, $unit));

        return $soldier;
    }
}
