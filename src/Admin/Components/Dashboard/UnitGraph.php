<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Admin\Components\Dashboard;

use Forumify\Admin\Components\Dashboard\TotalGraph;
use MajesticDev\CommandNet\Entity\Unit;
use MajesticDev\CommandNet\Repository\UnitRepository;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * @extends TotalGraph<Unit>
 */
#[AsTwigComponent('CommandNet\\Admin\\UnitGraph', '@Forumify/admin/dashboard/components/tile.html.twig')]
class UnitGraph extends TotalGraph
{
    public function __construct(UnitRepository $repository)
    {
        parent::__construct($repository);
    }

    public function getTitle(): string
    {
        return 'Units';
    }

    public function getIcon(): string
    {
        return 'ph-shield-star';
    }
}
