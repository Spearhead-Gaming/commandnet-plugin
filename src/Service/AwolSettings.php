<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Service;

use Forumify\Core\Repository\SettingRepository;

/**
 * Same shape as forumify-id-card-plugin's CardSettings: a single JSON-blob setting key,
 * wrapped so callers never touch the raw array.
 */
class AwolSettings
{
    public const array DEFAULTS = [
        'enabled' => false,
        'missThreshold' => 4,
        'role' => null,
    ];

    public function __construct(private readonly SettingRepository $settings)
    {
    }

    /**
     * @return array{enabled: bool, missThreshold: int, role: int|null}
     */
    public function all(): array
    {
        return array_replace(self::DEFAULTS, (array)$this->settings->get('command_net.awol'));
    }

    /**
     * @param array<string, mixed> $data
     */
    public function save(array $data): void
    {
        $this->settings->set('command_net.awol', array_intersect_key($data, self::DEFAULTS));
    }
}
