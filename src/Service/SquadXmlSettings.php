<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Service;

use Forumify\Core\Repository\SettingRepository;

/**
 * Same single-JSON-key shape as AwolSettings. The picture is the storage path of the uploaded logo.
 */
class SquadXmlSettings
{
    public const array DEFAULTS = [
        'enabled' => false,
        'nick' => '',
        'name' => '',
        'title' => '',
        'web' => '',
        'email' => '',
        'picture' => null,
    ];

    public function __construct(private readonly SettingRepository $settings)
    {
    }

    /**
     * @return array{enabled: bool, nick: string, name: string, title: string, web: string, email: string, picture: string|null}
     */
    public function all(): array
    {
        /** @var array{enabled: bool, nick: string, name: string, title: string, web: string, email: string, picture: string|null} */
        return array_replace(self::DEFAULTS, (array)$this->settings->get('command_net.squadxml'));
    }

    /**
     * @param array<string, mixed> $data
     */
    public function save(array $data): void
    {
        $this->settings->set('command_net.squadxml', array_intersect_key($data, self::DEFAULTS));
    }
}
