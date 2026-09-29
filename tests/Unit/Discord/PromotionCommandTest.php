<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Tests\Unit\Discord;

use Forumify\OAuth\Entity\IdentityProviderUser;
use Forumify\OAuth\Repository\IdentityProviderUserRepository;
use MajesticDev\CommandNet\Discord\Command\PromotionCommand;
use MajesticDev\CommandNet\Entity\Qualification;
use MajesticDev\CommandNet\Entity\Rank;
use MajesticDev\CommandNet\Repository\SoldierProfileRepository;
use MajesticDev\CommandNet\Service\PromotionEligibility;
use MajesticDev\CommandNet\Service\RankSettings;
use MajesticDev\CommandNet\Tests\Support\DiscordCommandTestCase;

class PromotionCommandTest extends DiscordCommandTestCase
{
    public function testRanksDisabledShortCircuits(): void
    {
        $result = $this->command(ranksEnabled: false)->run($this->invocation('command-net-promotion'));

        $this->assertSame('Ranks are disabled on this server.', $result->content);
    }

    public function testUnlinkedCallerIsToldToCoupleTheirAccount(): void
    {
        $result = $this->command(linked: false)->run($this->invocation('command-net-promotion'));

        $this->assertStringContainsString('could not find your personnel file', (string)$result->content);
        $this->assertStringContainsString('coupling your Discord account', (string)$result->content);
    }

    public function testSoldierAtTheTopOfTheirGroupHasNothingToShow(): void
    {
        $result = $this->command(row: null)->run($this->invocation('command-net-promotion'));

        $this->assertSame('You have no higher rank to be promoted into.', $result->content);
    }

    public function testEligibleSoldierSeesStatus(): void
    {
        $row = $this->row(eligible: true, missingDays: 0, missing: []);

        $result = $this->command(row: $row)->run($this->invocation('command-net-promotion'));

        $embed = $result->embeds[0];
        $this->assertSame('Promotion to Corporal', $embed->title);
        $fields = $this->fields($embed);
        $this->assertSame('Eligible', $fields['Status']);
        $this->assertSame('40', $fields['Days in rank']);
    }

    public function testIneligibleSoldierSeesWhatIsStillNeeded(): void
    {
        $qualification = $this->createStub(Qualification::class);
        $qualification->method('getName')->willReturn('Marksman');
        $row = $this->row(eligible: false, missingDays: 12, missing: [$qualification]);

        $result = $this->command(row: $row)->run($this->invocation('command-net-promotion'));

        $needed = $this->fields($result->embeds[0])['Still needed'];
        $this->assertStringContainsString('12 more days in rank', $needed);
        $this->assertStringContainsString('Marksman', $needed);
    }

    /**
     * @param array<Qualification> $missing
     * @return array<string, mixed>
     */
    private function row(bool $eligible, int $missingDays, array $missing): array
    {
        $next = $this->createStub(Rank::class);
        $next->method('getName')->willReturn('Corporal');

        return [
            'soldier' => $this->soldier(),
            'nextRank' => $next,
            'daysInGrade' => 40,
            'missingDays' => $missingDays,
            'missingQualifications' => $missing,
            'eligible' => $eligible,
        ];
    }

    /**
     * @param array<string, mixed>|null $row
     */
    private function command(bool $ranksEnabled = true, bool $linked = true, ?array $row = null): PromotionCommand
    {
        $user = $this->user();
        $link = null;
        if ($linked) {
            $link = $this->createStub(IdentityProviderUser::class);
            $link->method('getUser')->willReturn($user);
        }
        $idp = $this->createStub(IdentityProviderUserRepository::class);
        $idp->method('findOneByExternalIdAndIdpType')->willReturn($link);
        $soldiers = $this->createStub(SoldierProfileRepository::class);
        $soldiers->method('findOneBy')->willReturn($this->soldier($user));
        $eligibility = $this->createStub(PromotionEligibility::class);
        $eligibility->method('evaluateSoldier')->willReturn($row);
        $ranks = $this->createStub(RankSettings::class);
        $ranks->method('isEnabled')->willReturn($ranksEnabled);

        return new PromotionCommand($soldiers, $idp, $eligibility, $ranks);
    }
}
