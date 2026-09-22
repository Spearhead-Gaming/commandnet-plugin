<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Discord;

use Forumify\Core\Entity\User;
use Forumify\OAuth\Idp\DiscordIdp;
use Forumify\OAuth\Repository\IdentityProviderUserRepository;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Repository\SoldierProfileRepository;

/**
 * The "find who's calling" step every patrol command needs: the same linked-account
 * lookup PromotionCommand/SoldierCommand each did inline, pulled out now that five more
 * commands need it too.
 */
class DiscordUserResolver
{
    public function __construct(
        private readonly IdentityProviderUserRepository $idpUserRepository,
        private readonly SoldierProfileRepository $soldierProfileRepository,
    ) {
    }

    public function resolveUser(string $discordUserId): ?User
    {
        return $this->idpUserRepository
            ->findOneByExternalIdAndIdpType($discordUserId, DiscordIdp::getType())
            ?->getUser();
    }

    public function resolveSoldier(string $discordUserId): ?SoldierProfile
    {
        $user = $this->resolveUser($discordUserId);
        return $user !== null ? $this->soldierProfileRepository->findOneBy(['user' => $user]) : null;
    }
}
