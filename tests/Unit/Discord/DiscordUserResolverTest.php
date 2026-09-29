<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Tests\Unit\Discord;

use Forumify\Core\Entity\User;
use Forumify\OAuth\Entity\IdentityProviderUser;
use Forumify\OAuth\Idp\DiscordIdp;
use Forumify\OAuth\Repository\IdentityProviderUserRepository;
use MajesticDev\CommandNet\Discord\DiscordUserResolver;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Repository\SoldierProfileRepository;
use MajesticDev\CommandNet\Tests\Support\DiscordCommandTestCase;

class DiscordUserResolverTest extends DiscordCommandTestCase
{
    public function testLinkedAccountResolvesToTheForumUserAndTheirSoldier(): void
    {
        $user = $this->user();
        $soldier = $this->soldier($user);
        $resolver = $this->resolver($user, $soldier);

        $this->assertSame($user, $resolver->resolveUser('discord-1'));
        $this->assertSame($soldier, $resolver->resolveSoldier('discord-1'));
    }

    public function testUnlinkedDiscordAccountResolvesToNothing(): void
    {
        $resolver = $this->resolver(null, null);

        $this->assertNull($resolver->resolveUser('discord-1'));
        $this->assertNull($resolver->resolveSoldier('discord-1'));
    }

    public function testLinkedUserWithNoPersonnelFileHasNoSoldier(): void
    {
        $user = $this->user();
        $resolver = $this->resolver($user, null);

        $this->assertSame($user, $resolver->resolveUser('discord-1'));
        $this->assertNull($resolver->resolveSoldier('discord-1'));
    }

    private function resolver(?User $user, ?SoldierProfile $soldier): DiscordUserResolver
    {
        $link = null;
        if ($user !== null) {
            $link = $this->createStub(IdentityProviderUser::class);
            $link->method('getUser')->willReturn($user);
        }
        $idp = $this->createMock(IdentityProviderUserRepository::class);
        $idp->method('findOneByExternalIdAndIdpType')
            ->with('discord-1', DiscordIdp::getType())
            ->willReturn($link);
        $soldiers = $this->createStub(SoldierProfileRepository::class);
        $soldiers->method('findOneBy')->willReturn($soldier);

        return new DiscordUserResolver($idp, $soldiers);
    }
}
