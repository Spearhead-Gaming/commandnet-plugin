<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Admin\Components\Dashboard;

use MajesticDev\CommandNet\Entity\Enum\SoldierStatus;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('CommandNet\\Admin\\ActivePersonnelTile', '@CommandNetPlugin/admin/dashboard/status_tile.html.twig')]
class ActivePersonnelTile extends PersonnelStatusTile
{
    public function getTitle(): string
    {
        return 'Active Personnel';
    }

    public function getIcon(): string
    {
        return 'ph-user-check';
    }

    public function getStatus(): SoldierStatus
    {
        return SoldierStatus::ACTIVE;
    }
}
