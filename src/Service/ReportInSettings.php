<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Service;

use Forumify\Core\Repository\SettingRepository;

/**
 * Same single-JSON-key shape as AwolSettings.
 */
class ReportInSettings
{
    public const array DEFAULTS = [
        'enabled' => false,
        'periodDays' => 30,
        'warningDays' => 7,
    ];

    public function __construct(private readonly SettingRepository $settings)
    {
    }

    /**
     * @return array{enabled: bool, periodDays: int, warningDays: int}
     */
    public function all(): array
    {
        /** @var array{enabled: bool, periodDays: int, warningDays: int} */
        return array_replace(self::DEFAULTS, (array)$this->settings->get('command_net.report_in'));
    }

    /**
     * @param array<string, mixed> $data
     */
    public function save(array $data): void
    {
        $this->settings->set('command_net.report_in', array_intersect_key($data, self::DEFAULTS));
    }
}
