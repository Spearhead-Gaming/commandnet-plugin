<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Admin\Components\Dashboard;

use Forumify\Admin\Components\Dashboard\TotalGraph;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Repository\SoldierProfileRepository;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Reuses Forumify's own dashboard tile/graph pipeline (12-month running total, Chart.js line
 * graph) for the personnel count, the same way core's UserGraph does for forumify users -
 * TotalGraph only needs a repository whose entity has createdAt, which SoldierProfile already
 * does via TimestampableEntityTrait.
 *
 * @extends TotalGraph<SoldierProfile>
 */
#[AsTwigComponent('CommandNet\\Admin\\PersonnelGraph', '@Forumify/admin/dashboard/components/tile.html.twig')]
class PersonnelGraph extends TotalGraph
{
    public function __construct(SoldierProfileRepository $repository)
    {
        parent::__construct($repository);
    }

    public function getTitle(): string
    {
        return 'Personnel';
    }

    public function getIcon(): string
    {
        return 'ph-users-three';
    }
}
