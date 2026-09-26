<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Discord\Command;

use MajesticDev\Discord\Api\DTO\DiscordCommandResult;
use MajesticDev\Discord\Api\DTO\DiscordEmbed;
use MajesticDev\Discord\Api\Resource\DiscordCommandRun;
use MajesticDev\Discord\Discord\DiscordCommandInterface;
use Forumify\OAuth\Idp\DiscordIdp;
use Forumify\OAuth\Repository\IdentityProviderUserRepository;
use MajesticDev\CommandNet\Repository\SoldierProfileRepository;
use MajesticDev\CommandNet\Service\PromotionEligibility;
use MajesticDev\CommandNet\Service\RankSettings;

/**
 * Self-service only: shows the caller's own promotion progress via their linked Discord
 * account. The full roster view lives on the permissioned /promotions page, because
 * Discord commands here have no permission check and it would expose everyone's status.
 */
class PromotionCommand implements DiscordCommandInterface
{
    public function __construct(
        private readonly SoldierProfileRepository $soldierProfileRepository,
        private readonly IdentityProviderUserRepository $idpUserRepository,
        private readonly PromotionEligibility $promotionEligibility,
        private readonly RankSettings $rankSettings,
    ) {
    }

    public function getName(): string
    {
        return 'command-net-promotion';
    }

    public function getDescription(): string
    {
        return 'Shows your progress toward your next Command Net promotion.';
    }

    public function getOptions(): array
    {
        return [];
    }

    public function run(DiscordCommandRun $command): DiscordCommandResult
    {
        $result = new DiscordCommandResult();

        if (!$this->rankSettings->isEnabled()) {
            $result->content = 'Ranks are disabled on this server.';
            return $result;
        }

        $self = $this->idpUserRepository->findOneByExternalIdAndIdpType($command->discordUserId, DiscordIdp::getType());
        $profile = $self !== null ? $this->soldierProfileRepository->findOneBy(['user' => $self->getUser()]) : null;
        if ($profile === null) {
            $result->content = 'We could not find your personnel file. Try coupling your Discord account in your forum account settings.';
            return $result;
        }

        $row = $this->promotionEligibility->evaluateSoldier($profile);
        if ($row === null) {
            $result->content = 'You have no higher rank to be promoted into.';
            return $result;
        }

        $embed = new DiscordEmbed(title: 'Promotion to ' . $row['nextRank']->getName());
        $embed->addField('Days in rank', $row['daysInGrade'] !== null ? (string) $row['daysInGrade'] : 'Unknown', true);
        if ($row['eligible']) {
            $embed->addField('Status', 'Eligible', true);
        } else {
            $missing = [];
            if ($row['missingDays'] > 0) {
                $missing[] = $row['missingDays'] . ' more days in rank';
            }
            foreach ($row['missingQualifications'] as $qualification) {
                $missing[] = $qualification->getName();
            }
            $embed->addField('Still needed', implode("\n", $missing));
        }

        $result->embeds[] = $embed;
        return $result;
    }
}
