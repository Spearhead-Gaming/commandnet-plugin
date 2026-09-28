<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Tests\Unit\Service;

use Doctrine\ORM\EntityManagerInterface;
use MajesticDev\CommandNet\Entity\Position;
use MajesticDev\CommandNet\Entity\Squad;
use MajesticDev\CommandNet\Entity\Unit;
use MajesticDev\CommandNet\Repository\PositionRepository;
use MajesticDev\CommandNet\Service\OrbatImporter;
use MajesticDev\CommandNet\Service\PositionCatalog;
use PHPUnit\Framework\TestCase;

class OrbatImporterTest extends TestCase
{
    /**
     * @param array<Position> $existingPositions
     * @return array{0: OrbatImporter, 1: \ArrayObject<int, object>}
     */
    private function importer(array $existingPositions = []): array
    {
        /** @var \ArrayObject<int, object> $persisted */
        $persisted = new \ArrayObject();
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });
        $em->method('flush');

        $positions = $this->createMock(PositionRepository::class);
        $positions->method('findAll')->willReturn($existingPositions);

        return [new OrbatImporter($em, new PositionCatalog($em, $positions)), $persisted];
    }

    /**
     * @param array<object> $all
     * @return array<Unit>
     */
    private function units(array $all): array
    {
        return array_values(array_filter($all, static fn ($e) => $e instanceof Unit));
    }

    /**
     * @param array<object> $all
     * @return array<Squad>
     */
    private function squads(array $all): array
    {
        return array_values(array_filter($all, static fn ($e) => $e instanceof Squad));
    }

    public function testBuildsNestedUnitsAndLinksPositionsToTheirOwningUnit(): void
    {
        [$importer, $persisted] = $this->importer();

        $result = $importer->import(<<<OUTLINE
            Misfit Platoon
              = Platoon Lead
              Weapons Det
                = Squad Leader
                = Squad Leader
            OUTLINE);

        self::assertSame(['units' => 2, 'squads' => 0, 'positions' => 2], $result);

        $units = $this->units($persisted->getArrayCopy());
        self::assertSame(['Misfit Platoon', 'Weapons Det'], array_map(static fn (Unit $u) => $u->getName(), $units));
        self::assertNull($units[0]->getParent());
        self::assertSame($units[0], $units[1]->getParent());

        self::assertSame(['Platoon Lead'], array_map(static fn (Position $p) => $p->getTitle(), $units[0]->getPositions()->toArray()));
        self::assertSame(['Squad Leader'], array_map(static fn (Position $p) => $p->getTitle(), $units[1]->getPositions()->toArray()));
    }

    public function testBuildsSquadsAndTeamsUnderAUnitWithPositionsLinkedToTheOwningNode(): void
    {
        [$importer, $persisted] = $this->importer();

        $result = $importer->import(<<<OUTLINE
            Detachment 7
              + Squad 1
                = Squad Leader
                + Team 1
                  = Team Leader
            OUTLINE);

        self::assertSame(['units' => 1, 'squads' => 2, 'positions' => 2], $result);

        $all = $persisted->getArrayCopy();
        $unit = $this->units($all)[0];
        $squads = $this->squads($all);
        self::assertSame(['Squad 1', 'Team 1'], array_map(static fn (Squad $s) => $s->getName(), $squads));

        [$squad1, $team1] = $squads;
        self::assertSame($unit, $squad1->getUnit());
        self::assertNull($squad1->getParent());
        self::assertSame($unit, $team1->getUnit());
        self::assertSame($squad1, $team1->getParent());

        self::assertSame(['Squad Leader'], array_map(static fn (Position $p) => $p->getTitle(), $squad1->getPositions()->toArray()));
        self::assertSame(['Team Leader'], array_map(static fn (Position $p) => $p->getTitle(), $team1->getPositions()->toArray()));
    }

    public function testTopLevelLinesAttachUnderAGivenParent(): void
    {
        [$importer, $persisted] = $this->importer();
        $parent = new Unit();
        $parent->setName('Existing Company');

        $importer->import('New Platoon', $parent);

        /** @var Unit $unit */
        $unit = $persisted->getArrayCopy()[0];
        self::assertSame($parent, $unit->getParent());
    }

    public function testReusesAnExistingPositionCaseInsensitivelyAndLinksIt(): void
    {
        $existing = new Position();
        $existing->setTitle('Squad Leader');
        [$importer, $persisted] = $this->importer([$existing]);

        $result = $importer->import("Detachment 7\n  + Squad 1\n    = squad leader");

        self::assertSame(['units' => 1, 'squads' => 1, 'positions' => 1], $result);
        $squads = $this->squads($persisted->getArrayCopy());
        self::assertSame([$existing], $squads[0]->getPositions()->toArray());
    }

    public function testAtPresetExpandsToItsStandardPositionsOnTheOwningSquad(): void
    {
        [$importer, $persisted] = $this->importer();

        $result = $importer->import("Detachment 7\n  + Squad 1\n    + Team 1\n      @Team");

        self::assertSame(['units' => 1, 'squads' => 2, 'positions' => 3], $result);
        $squads = $this->squads($persisted->getArrayCopy());
        self::assertSame(
            PositionCatalog::PRESETS['Team'],
            array_map(static fn (Position $p) => $p->getTitle(), $squads[1]->getPositions()->toArray()),
        );
        self::assertSame([], $squads[0]->getPositions()->toArray());
    }

    public function testCommentLinesAreIgnoredRegardlessOfIndentation(): void
    {
        [$importer, $persisted] = $this->importer();

        $result = $importer->import("# A top-level comment\nDetachment 7\n    # An oddly-indented comment\n  = Commanding Officer");

        self::assertSame(['units' => 1, 'squads' => 0, 'positions' => 1], $result);
        self::assertCount(2, $persisted); // the unit plus the one linked position
    }

    public function testRejectsAUnitNestedUnderASquad(): void
    {
        [$importer] = $this->importer();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage("can't be nested under a squad/team");
        $importer->import("Detachment 7\n  + Squad 1\n    Nested Unit");
    }

    public function testRejectsASquadWithNoEnclosingUnitOrSquad(): void
    {
        [$importer] = $this->importer();

        $this->expectException(\DomainException::class);
        $importer->import('+ Squad 1');
    }

    public function testUnknownPresetIsRejected(): void
    {
        [$importer] = $this->importer();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('unknown preset "@Nonsense"');
        $importer->import("Detachment 7\n  + Squad 1\n    @Nonsense");
    }

    public function testRejectsIndentationNotInMultiplesOfTwoSpaces(): void
    {
        [$importer] = $this->importer();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Line 2');
        $importer->import("Platoon\n   Squad 1");
    }

    public function testRejectsAPositionLineAtTheTopLevel(): void
    {
        [$importer] = $this->importer();

        $this->expectException(\DomainException::class);
        $importer->import('= Platoon Lead');
    }

    public function testRejectsIndentationThatSkipsALevel(): void
    {
        [$importer] = $this->importer();

        $this->expectException(\DomainException::class);
        $importer->import("Platoon\n    Squad 1");
    }
}
