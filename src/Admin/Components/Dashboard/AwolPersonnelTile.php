<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Admin\Components\Dashboard;

use MajesticDev\CommandNet\Entity\Enum\SoldierStatus;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('CommandNet\\Admin\\AwolPersonnelTile', '@CommandNetPlugin/admin/dashboard/status_tile.html.twig')]
class AwolPersonnelTile extends PersonnelStatusTile
{
    public function getTitle(): string
    {
        return 'AWOL';
    }

    public function getIcon(): string
    {
        return 'ph-warning';
    }

    public function getStatus(): SoldierStatus
    {
        return SoldierStatus::AWOL;
    }
}
