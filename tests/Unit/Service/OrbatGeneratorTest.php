<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Tests\Unit\Service;

use Forumify\Core\Entity\User;
use MajesticDev\CommandNet\Entity\Enum\EquipmentType;
use MajesticDev\CommandNet\Entity\Equipment;
use MajesticDev\CommandNet\Entity\Rank;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Entity\Unit;
use MajesticDev\CommandNet\Service\OrbatGenerator;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class OrbatGeneratorTest extends TestCase
{
    public function testUnitsBecomeNestedGroupsWithSizesWorkedOutFromDepth(): void
    {
        $company = $this->unit(1, 'Alpha Company', 'A Co');
        $platoon = $this->unit(2, 'First Platoon', '1 Plt');
        $squad = $this->unit(3, 'Alpha Squad', 'A Sqd');
        $this->child($company, $platoon);
        $this->child($platoon, $squad);

        $config = (new OrbatGenerator())->generate([$company], 'West');

        $this->assertStringContainsString('class Unit_1', $config);
        $this->assertStringContainsString('size = "Company";', $config);
        $this->assertStringContainsString('size = "Platoon";', $config);
        $this->assertStringContainsString('size = "Squad";', $config);
        $this->assertLessThan(strpos($config, 'class Unit_2'), strpos($config, 'class Unit_1'));
        $this->assertLessThan(strpos($config, 'class Unit_3'), strpos($config, 'class Unit_2'));
        $this->assertSame(substr_count($config, '{'), substr_count($config, '}'));
    }

    public function testAnExplicitSizeAndTypeWinAndBlankTypeIsInfantry(): void
    {
        $unit = $this->unit(1, 'Armor Section', 'AS');
        $unit->setOrbatSize('Company');
        $unit->setOrbatType('Armored');
        $plain = $this->unit(2, 'Rifles', 'R');

        $config = (new OrbatGenerator())->generate([$unit, $plain], 'East');

        $this->assertStringContainsString('size = "Company";', $config);
        $this->assertStringContainsString('type = "Armored";', $config);
        $this->assertStringContainsString('type = "Infantry";', $config);
        $this->assertStringContainsString('side = "East";', $config);
    }

    public function testAnUnknownSideFallsBackToWest(): void
    {
        $this->assertStringContainsString('side = "West";', (new OrbatGenerator())->generate([$this->unit(1, 'A', 'A')], 'nonsense'));
    }

    public function testVehiclesWithAClassnameBecomeCountedAssets(): void
    {
        $unit = $this->unit(1, 'Motor Pool', 'MP');
        $unit->addVehicle($this->vehicle('B_MRAP_01_F'));
        $unit->addVehicle($this->vehicle('B_MRAP_01_F'));
        $unit->addVehicle($this->vehicle('B_Heli_Light_01_F'));
        $unit->addVehicle($this->vehicle(null));

        $config = (new OrbatGenerator())->generate([$unit], 'West');

        $this->assertStringContainsString('assets[] = {{"B_MRAP_01_F", 2}, {"B_Heli_Light_01_F", 1}};', $config);
    }

    public function testNoAssetsLineWhenNoVehicleHasAClassname(): void
    {
        $unit = $this->unit(1, 'Motor Pool', 'MP');
        $unit->addVehicle($this->vehicle(null));

        $this->assertStringNotContainsString('assets', (new OrbatGenerator())->generate([$unit], 'West'));
    }

    public function testCommanderAndRankAreExported(): void
    {
        $rank = new Rank();
        $rank->setName('Captain');
        $commander = new SoldierProfile(new User());
        $commander->setCallsign('Ghost');
        $commander->setRank($rank);
        $unit = $this->unit(1, 'HQ', 'HQ');
        $unit->setCommander($commander);

        $config = (new OrbatGenerator())->generate([$unit], 'West');

        $this->assertStringContainsString('commander = "Ghost";', $config);
        $this->assertStringContainsString('commanderRank = "Captain";', $config);
    }

    public function testQuotesAreDoubledAndLineBreaksDropped(): void
    {
        $unit = $this->unit(1, 'The "Best" Unit', 'TBU');
        $unit->setDescription("Line one\r\nLine two");

        $config = (new OrbatGenerator())->generate([$unit], 'West');

        $this->assertStringContainsString('text = "The ""Best"" Unit";', $config);
        $this->assertStringContainsString('description = "Line one Line two";', $config);
    }

    private function unit(int $id, string $name, string $abbreviation): Unit
    {
        $unit = new Unit();
        $unit->setName($name);
        $unit->setAbbreviation($abbreviation);
        (new ReflectionProperty($unit, 'id'))->setValue($unit, $id);

        return $unit;
    }

    private function child(Unit $parent, Unit $child): void
    {
        $child->setParent($parent);
        $parent->getChildren()->add($child);
    }

    private function vehicle(?string $classname): Equipment
    {
        $equipment = new Equipment();
        $equipment->setName($classname ?? 'Unmapped');
        $equipment->setType(EquipmentType::VEHICLE);
        $equipment->setClassname($classname);

        return $equipment;
    }
}
