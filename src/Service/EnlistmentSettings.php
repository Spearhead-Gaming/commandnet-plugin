<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Service;

use Forumify\Core\Repository\SettingRepository;

/**
 * Same single-JSON-key shape as AwolSettings. Rank and unit are stored as plain ids.
 */
class EnlistmentSettings
{
    public const array DEFAULTS = [
        'enabled' => false,
        'defaultRank' => null,
        'defaultUnit' => null,
        'instructions' => '',
    ];

    public function __construct(private readonly SettingRepository $settings)
    {
    }

    /**
     * @return array{enabled: bool, defaultRank: int|null, defaultUnit: int|null, instructions: string}
     */
    public function all(): array
    {
        /** @var array{enabled: bool, defaultRank: int|null, defaultUnit: int|null, instructions: string} */
        return array_replace(self::DEFAULTS, (array)$this->settings->get('command_net.enlistment'));
    }

    /**
     * @param array<string, mixed> $data
     */
    public function save(array $data): void
    {
        $this->settings->set('command_net.enlistment', array_intersect_key($data, self::DEFAULTS));
    }
}
