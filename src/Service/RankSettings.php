<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Service;

use Forumify\Core\Repository\SettingRepository;

/**
 * Same single-JSON-key shape as AwolSettings/EnlistmentSettings. Ranks stay fully
 * functional under the hood when disabled - this only hides rank UI and promotion
 * flows so a community that doesn't use ranks can turn it back on without data loss.
 */
class RankSettings
{
    public const array DEFAULTS = [
        'enabled' => true,
    ];

    public function __construct(private readonly SettingRepository $settings)
    {
    }

    /**
     * @return array{enabled: bool}
     */
    public function all(): array
    {
        /** @var array{enabled: bool} */
        return array_replace(self::DEFAULTS, (array)$this->settings->get('command_net.rank'));
    }

    public function isEnabled(): bool
    {
        return $this->all()['enabled'];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function save(array $data): void
    {
        $this->settings->set('command_net.rank', array_intersect_key($data, self::DEFAULTS));
    }
}
