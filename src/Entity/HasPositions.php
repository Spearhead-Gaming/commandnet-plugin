<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Entity;

use Doctrine\Common\Collections\Collection;

/**
 * Implemented by both Unit and Squad, the two things a billet (Position) can be linked to -
 * lets OrbatImporter treat "the nearest enclosing thing a position line is indented under"
 * uniformly regardless of whether that's a real command or a squad/team within one.
 */
interface HasPositions
{
    /**
     * @return Collection<int, Position>
     */
    public function getPositions(): Collection;

    public function addPosition(Position $position): void;
}
