<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Discord\Command;

use MajesticDev\Discord\Api\DTO\DiscordCommandOption;
use MajesticDev\Discord\Api\DTO\DiscordCommandResult;
use MajesticDev\Discord\Api\DTO\DiscordEmbed;
use MajesticDev\Discord\Api\Resource\DiscordCommandRun;
use MajesticDev\Discord\Discord\DiscordCommandInterface;
use Forumify\OAuth\Idp\DiscordIdp;
use Forumify\OAuth\Repository\IdentityProviderUserRepository;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Repository\SoldierProfileRepository;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\UrlHelper;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Mirrors MILHQ's "milhq-soldier" Discord command: same self-lookup-via-linked-Discord-
 * account fallback, same embed shape, just pointed at Command Net's own SoldierProfile.
 */
class SoldierCommand implements DiscordCommandInterface
{
    public function __construct(
        private readonly SoldierProfileRepository $soldierProfileRepository,
        private readonly IdentityProviderUserRepository $idpUserRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly Packages $packages,
        private readonly UrlHelper $urlHelper,
    ) {
    }

    public function getName(): string
    {
        return 'command-net-soldier';
    }

    public function getDescription(): string
    {
        return "Shows a Command Net soldier's personnel file.";
    }

    public function getOptions(): array
    {
        return [
            new DiscordCommandOption()
                ->setName('name')
                ->setDescription('Optional soldier name, if left blank it will show your own profile.'),
        ];
    }

    public function run(DiscordCommandRun $command): DiscordCommandResult
    {
        $result = new DiscordCommandResult();

        $profile = $this->getProfileFromCmd($command);
        if ($profile === null) {
            $result->content = "We could not find any personnel file matching your request. Try coupling your Discord account in your forum account settings, or provide the `name` option to the command.";
            return $result;
        }

        $result->embeds[] = $this->createEmbed($profile);
        return $result;
    }

    private function getProfileFromCmd(DiscordCommandRun $command): ?SoldierProfile
    {
        $name = $command->options['name'] ?? null;
        if (!empty($name)) {
            $res = $this->soldierProfileRepository->findByNameLike($name);
            return reset($res) ?: null;
        }

        $self = $this->idpUserRepository->findOneByExternalIdAndIdpType($command->discordUserId, DiscordIdp::getType());
        if ($self === null) {
            return null;
        }

        return $this->soldierProfileRepository->findOneBy(['user' => $self->getUser()]);
    }

    private function createEmbed(SoldierProfile $profile): DiscordEmbed
    {
        $embed = new DiscordEmbed(
            title: $profile->getUser()->getDisplayName(),
            url: $this->urlGenerator->generate('command_net_roster_profile', [
                'username' => $profile->getUser()->getUsername(),
            ], UrlGeneratorInterface::ABSOLUTE_URL),
        );

        $rank = $profile->getRank();
        if ($rank !== null) {
            $embed->title = $rank->getAbbreviation() . ' ' . $embed->title;
            $embed->addField('Rank', trim($rank->getPayGrade() . ' ' . $rank->getName()));

            $rankImg = $rank->getInsignia();
            if ($rankImg) {
                $rankUrl = $this->packages->getUrl($rankImg, 'forumify.asset');
                $embed->setThumbnail($this->urlHelper->getAbsoluteUrl($rankUrl));
            }
        }

        $embed->addField('Status', $profile->getStatus()->label(), true);

        $callsign = $profile->getCallsign();
        if (!empty($callsign)) {
            $embed->addField('Callsign', $callsign, true);
        }

        $assignment = $profile->getPrimaryAssignment();
        if ($assignment !== null) {
            $parts = [$assignment->getUnit()->getName()];
            if ($position = $assignment->getPosition()) {
                $parts[] = $position->getTitle();
            }
            $embed->addField('Assignment', implode(' - ', $parts));
        }

        $steamId = $profile->getSteamId();
        if (!empty($steamId)) {
            $embed->addField('Steam', "https://steamcommunity.com/profiles/{$steamId}");
        }

        $uniform = $profile->getUniformImage();
        if ($uniform) {
            $uniformUrl = $this->packages->getUrl($uniform, 'forumify.asset');
            $embed->setImage($this->urlHelper->getAbsoluteUrl($uniformUrl));
        }

        return $embed;
    }
}
