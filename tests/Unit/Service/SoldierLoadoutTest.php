<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Tests\Unit\Service;

use DateTime;
use Forumify\Core\Entity\User;
use MajesticDev\CommandNet\Entity\Assignment;
use MajesticDev\CommandNet\Entity\Enum\EquipmentType;
use MajesticDev\CommandNet\Entity\Equipment;
use MajesticDev\CommandNet\Entity\Position;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Entity\Unit;
use MajesticDev\CommandNet\Service\SoldierLoadout;
use PHPUnit\Framework\TestCase;

class SoldierLoadoutTest extends TestCase
{
    public function testLoadoutComesFromThePositionWeaponsAndTheUnitVehicles(): void
    {
        $rifle = $this->equipment('M4A1', EquipmentType::PRIMARY_WEAPON);
        $pistol = $this->equipment('M9', EquipmentType::SECONDARY_WEAPON);
        $truck = $this->equipment('HMMWV', EquipmentType::VEHICLE);

        $position = new Position();
        $position->addPrimaryWeapon($rifle);
        $position->addSecondaryWeapon($pistol);
        $unit = new Unit();
        $unit->addVehicle($truck);

        $soldier = new SoldierProfile(new User());
        $assignment = new Assignment($soldier, $unit);
        $assignment->setPosition($position);
        $soldier->addAssignment($assignment);

        $loadout = (new SoldierLoadout())->forSoldier($soldier);

        $this->assertSame([$rifle], $loadout['primaryWeapons']);
        $this->assertSame([$pistol], $loadout['secondaryWeapons']);
        $this->assertSame([$truck], $loadout['vehicles']);
    }

    public function testAssignmentWithoutAPositionStillGetsTheUnitVehicles(): void
    {
        $truck = $this->equipment('HMMWV', EquipmentType::VEHICLE);
        $unit = new Unit();
        $unit->addVehicle($truck);
        $soldier = new SoldierProfile(new User());
        $soldier->addAssignment(new Assignment($soldier, $unit));

        $loadout = (new SoldierLoadout())->forSoldier($soldier);

        $this->assertSame([], $loadout['primaryWeapons']);
        $this->assertSame([$truck], $loadout['vehicles']);
    }

    public function testNoCurrentAssignmentMeansAnEmptyLoadout(): void
    {
        $unit = new Unit();
        $unit->addVehicle($this->equipment('HMMWV', EquipmentType::VEHICLE));
        $soldier = new SoldierProfile(new User());
        $ended = new Assignment($soldier, $unit);
        $ended->setEndDate(new DateTime());
        $soldier->addAssignment($ended);

        $this->assertSame(
            ['primaryWeapons' => [], 'secondaryWeapons' => [], 'vehicles' => []],
            (new SoldierLoadout())->forSoldier($soldier),
        );
    }

    public function testAddingTheSameEquipmentTwiceKeepsOneEntry(): void
    {
        $rifle = $this->equipment('M4A1', EquipmentType::PRIMARY_WEAPON);
        $position = new Position();
        $position->addPrimaryWeapon($rifle);
        $position->addPrimaryWeapon($rifle);

        $this->assertCount(1, $position->getPrimaryWeapons());
    }

    private function equipment(string $name, EquipmentType $type): Equipment
    {
        $equipment = new Equipment();
        $equipment->setName($name);
        $equipment->setType($type);

        return $equipment;
    }
}
