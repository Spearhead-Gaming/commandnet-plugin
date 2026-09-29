<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Tests\Support;

use Forumify\Core\Entity\User;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\Discord\Api\DTO\DiscordEmbed;
use MajesticDev\Discord\Api\Resource\DiscordCommandRun;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Shared builders for the Discord command tests: a command invocation, a soldier, an embed's
 * fields by name, and a URL generator that returns a predictable link.
 */
abstract class DiscordCommandTestCase extends TestCase
{
    protected const string URL = 'https://forum.test/patrol';

    /**
     * @param array<string, mixed> $options
     */
    protected function invocation(string $name, array $options = [], string $discordUserId = 'discord-1'): DiscordCommandRun
    {
        $run = new DiscordCommandRun();
        $run->name = $name;
        $run->options = $options;
        $run->discordUserId = $discordUserId;

        return $run;
    }

    protected function user(string $displayName = 'Alice', string $username = 'alice'): User
    {
        $user = $this->createStub(User::class);
        $user->method('getDisplayName')->willReturn($displayName);
        $user->method('getUsername')->willReturn($username);

        return $user;
    }

    protected function soldier(?User $user = null): SoldierProfile
    {
        return new SoldierProfile($user ?? $this->user());
    }

    /**
     * Doctrine assigns ids on flush; give an in-memory entity the id a saved one would have.
     */
    protected function withId(object $entity, int $id): void
    {
        (new ReflectionProperty($entity, 'id'))->setValue($entity, $id);
    }

    protected function urls(): UrlGeneratorInterface
    {
        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturn(self::URL);

        return $urls;
    }

    /**
     * @return array<string, string>
     */
    protected function fields(DiscordEmbed $embed): array
    {
        $fields = [];
        foreach ($embed->fields ?? [] as $field) {
            $fields[$field['name']] = $field['value'];
        }

        return $fields;
    }
}
