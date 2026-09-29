<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Tests\Unit\Discord;

use DateTime;
use MajesticDev\CommandNet\Discord\Command\UnitCommand;
use MajesticDev\CommandNet\Entity\Assignment;
use MajesticDev\CommandNet\Entity\Unit;
use MajesticDev\CommandNet\Repository\UnitRepository;
use MajesticDev\CommandNet\Service\RankSettings;
use MajesticDev\CommandNet\Tests\Support\DiscordCommandTestCase;

class UnitCommandTest extends DiscordCommandTestCase
{
    public function testShowsAbbreviationCommanderAndActiveRoster(): void
    {
        $unit = new Unit();
        $unit->setName('1st Squad');
        $unit->setAbbreviation('1SQ');
        $unit->setCommander($this->soldier($this->user('Boss')));
        $active = new Assignment($this->soldier($this->user('Active Andy')), $unit);
        $ended = new Assignment($this->soldier($this->user('Gone Gary')), $unit);
        $ended->setEndDate(new DateTime('-1 day'));
        $unit->getAssignments()->add($active);
        $unit->getAssignments()->add($ended);
        $units = $this->createMock(UnitRepository::class);
        $units->expects($this->once())->method('findByNameLike')->with('1st')->willReturn([$unit]);

        $result = $this->command($units)->run($this->invocation('command-net-unit', ['name' => ' 1st ']));

        $embed = $result->embeds[0];
        $this->assertSame('1st Squad', $embed->title);
        $fields = $this->fields($embed);
        $this->assertSame('1SQ', $fields['Abbreviation']);
        $this->assertSame('Boss', $fields['Commander']);
        $this->assertStringContainsString('Active Andy', $fields['Roster']);
        $this->assertStringNotContainsString('Gone Gary', $fields['Roster'], 'Ended assignments are not on the roster.');
    }

    public function testBlankNameIsRefusedWithoutSearching(): void
    {
        $units = $this->createMock(UnitRepository::class);
        $units->expects($this->never())->method('findByNameLike');

        $result = $this->command($units)->run($this->invocation('command-net-unit', ['name' => '  ']));

        $this->assertSame('Please provide a unit name to search for.', $result->content);
    }

    public function testNoMatchNamesTheSearch(): void
    {
        $units = $this->createStub(UnitRepository::class);
        $units->method('findByNameLike')->willReturn([]);

        $result = $this->command($units)->run($this->invocation('command-net-unit', ['name' => 'Ghost Unit']));

        $this->assertSame('We could not find any units matching "Ghost Unit".', $result->content);
        $this->assertSame([], $result->embeds);
    }

    private function command(UnitRepository $units): UnitCommand
    {
        $ranks = $this->createStub(RankSettings::class);
        $ranks->method('isEnabled')->willReturn(false);

        return new UnitCommand($units, $this->urls(), $ranks);
    }
}
