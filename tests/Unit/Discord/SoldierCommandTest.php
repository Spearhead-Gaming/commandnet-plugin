<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Tests\Unit\Discord;

use Forumify\OAuth\Entity\IdentityProviderUser;
use Forumify\OAuth\Repository\IdentityProviderUserRepository;
use MajesticDev\CommandNet\Discord\Command\SoldierCommand;
use MajesticDev\CommandNet\Entity\Enum\SoldierStatus;
use MajesticDev\CommandNet\Entity\Equipment;
use MajesticDev\CommandNet\Entity\Specialty;
use MajesticDev\CommandNet\Repository\SoldierProfileRepository;
use MajesticDev\CommandNet\Service\RankSettings;
use MajesticDev\CommandNet\Service\SoldierLoadout;
use MajesticDev\CommandNet\Tests\Support\DiscordCommandTestCase;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\UrlHelper;

class SoldierCommandTest extends DiscordCommandTestCase
{
    public function testLooksUpBySearchNameAndShowsTheirFile(): void
    {
        $soldier = $this->soldier($this->user('Alice Smith', 'alice'));
        $soldier->setCallsign('Ghost');
        $soldier->setStatus(SoldierStatus::ACTIVE);
        $soldiers = $this->createMock(SoldierProfileRepository::class);
        $soldiers->expects($this->once())->method('findByNameLike')->with('alice')->willReturn([$soldier]);

        $result = $this->command($soldiers)->run($this->invocation('command-net-soldier', ['name' => 'alice']));

        $this->assertNull($result->content);
        $this->assertCount(1, $result->embeds);
        $embed = $result->embeds[0];
        $this->assertSame('Alice Smith', $embed->title);
        $this->assertSame(self::URL, $embed->url);
        $fields = $this->fields($embed);
        $this->assertSame('Ghost', $fields['Callsign']);
        $this->assertSame(SoldierStatus::ACTIVE->label(), $fields['Status']);
        $this->assertArrayNotHasKey('Steam', $fields, 'Empty optional fields are skipped, not shown blank.');
    }

    public function testNoNameFallsBackToTheCallersLinkedAccount(): void
    {
        $user = $this->user('Me', 'me');
        $soldier = $this->soldier($user);
        $link = $this->createStub(IdentityProviderUser::class);
        $link->method('getUser')->willReturn($user);
        $idp = $this->createMock(IdentityProviderUserRepository::class);
        $idp->expects($this->once())->method('findOneByExternalIdAndIdpType')->with('discord-9')->willReturn($link);
        $soldiers = $this->createStub(SoldierProfileRepository::class);
        $soldiers->method('findOneBy')->willReturn($soldier);

        $result = $this->command($soldiers, $idp)->run($this->invocation('command-net-soldier', [], 'discord-9'));

        $this->assertSame('Me', $result->embeds[0]->title);
    }

    public function testUnknownNameSaysSoInsteadOfAnEmbed(): void
    {
        $soldiers = $this->createStub(SoldierProfileRepository::class);
        $soldiers->method('findByNameLike')->willReturn([]);

        $result = $this->command($soldiers)->run($this->invocation('command-net-soldier', ['name' => 'nobody']));

        $this->assertSame([], $result->embeds);
        $this->assertStringContainsString('could not find any personnel file', (string)$result->content);
    }

    public function testUnlinkedCallerWithNoNameGetsGuidance(): void
    {
        $idp = $this->createStub(IdentityProviderUserRepository::class);
        $idp->method('findOneByExternalIdAndIdpType')->willReturn(null);

        $result = $this->command($this->createStub(SoldierProfileRepository::class), $idp)->run($this->invocation('command-net-soldier'));

        $this->assertStringContainsString('coupling your Discord account', (string)$result->content);
    }

    public function testShowsSpecialtyAndALoadoutLinePerKindOfKit(): void
    {
        $specialty = new Specialty();
        $specialty->setName('Medic');
        $soldier = $this->soldier();
        $soldier->setSpecialty($specialty);
        $soldiers = $this->createStub(SoldierProfileRepository::class);
        $soldiers->method('findByNameLike')->willReturn([$soldier]);
        $loadout = ['primaryWeapons' => [$this->gear('M4A1'), $this->gear('MK18')], 'secondaryWeapons' => [], 'vehicles' => [$this->gear('HMMWV')]];

        $fields = $this->fields($this->command($soldiers, loadout: $loadout)->run($this->invocation('command-net-soldier', ['name' => 'alice']))->embeds[0]);

        $this->assertSame('Medic', $fields['Specialty']);
        $this->assertSame("Primary: M4A1, MK18\nVehicles: HMMWV", $fields['Loadout'], 'Empty kinds of kit are left out.');
    }

    public function testOmitsSpecialtyAndLoadoutWhenThereIsNone(): void
    {
        $soldiers = $this->createStub(SoldierProfileRepository::class);
        $soldiers->method('findByNameLike')->willReturn([$this->soldier()]);

        $fields = $this->fields($this->command($soldiers)->run($this->invocation('command-net-soldier', ['name' => 'alice']))->embeds[0]);

        $this->assertArrayNotHasKey('Specialty', $fields);
        $this->assertArrayNotHasKey('Loadout', $fields);
    }

    private function gear(string $name): Equipment
    {
        $equipment = new Equipment();
        $equipment->setName($name);

        return $equipment;
    }

    /**
     * @param array{primaryWeapons: array<Equipment>, secondaryWeapons: array<Equipment>, vehicles: array<Equipment>}|null $loadout
     */
    private function command(SoldierProfileRepository $soldiers, ?IdentityProviderUserRepository $idp = null, ?array $loadout = null): SoldierCommand
    {
        $ranks = $this->createStub(RankSettings::class);
        $ranks->method('isEnabled')->willReturn(false);
        $soldierLoadout = $this->createStub(SoldierLoadout::class);
        $soldierLoadout->method('forSoldier')->willReturn($loadout ?? ['primaryWeapons' => [], 'secondaryWeapons' => [], 'vehicles' => []]);

        return new SoldierCommand(
            $soldiers,
            $idp ?? $this->createStub(IdentityProviderUserRepository::class),
            $this->urls(),
            $this->createStub(Packages::class),
            $this->createStub(UrlHelper::class),
            $ranks,
            $soldierLoadout,
        );
    }
}
