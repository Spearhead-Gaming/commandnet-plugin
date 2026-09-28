<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Admin\Components\Dashboard;

use MajesticDev\CommandNet\Entity\Enum\SoldierStatus;
use MajesticDev\CommandNet\Repository\SoldierProfileRepository;

/**
 * A right-now headcount for one status (see ActivePersonnelTile/AwolPersonnelTile) - unlike
 * TotalGraph's cumulative total over time, "how many are currently AWOL" doesn't make sense
 * as a 12-month trend, so this is a plain count instead of a graph.
 */
abstract class PersonnelStatusTile
{
    public function __construct(private readonly SoldierProfileRepository $repository)
    {
    }

    public function getCount(): int
    {
        return $this->repository->count(['status' => $this->getStatus()]);
    }

    abstract public function getTitle(): string;

    abstract public function getIcon(): string;

    abstract public function getStatus(): SoldierStatus;
}
