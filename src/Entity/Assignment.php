<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Forumify\Core\Entity\IdentifiableEntityTrait;
use MajesticDev\CommandNet\Repository\AssignmentRepository;

/**
 * A soldier's posting to a unit, optionally in a specific position, over a date range.
 *
 * Keeping this as its own entity (rather than a single "current unit" field on
 * SoldierProfile) is what lets us show assignment history and support secondary/attached
 * postings without losing the record when someone transfers.
 */
#[ORM\Entity(AssignmentRepository::class)]
class Assignment
{
    use IdentifiableEntityTrait;

    #[ORM\ManyToOne(targetEntity: SoldierProfile::class, inversedBy: 'assignments')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private SoldierProfile $soldier;

    #[ORM\ManyToOne(targetEntity: Unit::class, inversedBy: 'assignments')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Unit $unit;

    #[ORM\ManyToOne(targetEntity: Position::class)]
    #[ORM\JoinColumn(name: 'position_id', onDelete: 'SET NULL')]
    private ?Position $position = null;

    /**
     * Which squad/team within the unit, if any - independent of position, since a squad/team
     * is a grouping (Squad::unit is always this same unit) rather than a command in its own
     * right, so it isn't itself an assignable "unit".
     */
    #[ORM\ManyToOne(targetEntity: Squad::class)]
    #[ORM\JoinColumn(name: 'squad_id', onDelete: 'SET NULL')]
    private ?Squad $squad = null;

    /**
     * A soldier may hold several assignments at once, but only one should be primary —
     * that's the one used for the roster display, forum sync, and org chart placement.
     */
    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $isPrimary = true;

    #[ORM\Column(type: 'date')]
    private DateTime $startDate;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?DateTime $endDate = null;

    public function __construct(SoldierProfile $soldier, Unit $unit)
    {
        $this->soldier = $soldier;
        $this->unit = $unit;
        $this->startDate = new DateTime();
    }

    public function getSoldier(): SoldierProfile
    {
        return $this->soldier;
    }

    public function getUnit(): Unit
    {
        return $this->unit;
    }

    public function setUnit(Unit $unit): void
    {
        $this->unit = $unit;
    }

    public function getPosition(): ?Position
    {
        return $this->position;
    }

    public function setPosition(?Position $position): void
    {
        $this->position = $position;
    }

    public function getSquad(): ?Squad
    {
        return $this->squad;
    }

    public function setSquad(?Squad $squad): void
    {
        $this->squad = $squad;
    }

    public function isPrimary(): bool
    {
        return $this->isPrimary;
    }

    public function setIsPrimary(bool $isPrimary): void
    {
        $this->isPrimary = $isPrimary;
    }

    public function getStartDate(): DateTime
    {
        return $this->startDate;
    }

    public function setStartDate(DateTime $startDate): void
    {
        $this->startDate = $startDate;
    }

    public function getEndDate(): ?DateTime
    {
        return $this->endDate;
    }

    public function setEndDate(?DateTime $endDate): void
    {
        $this->endDate = $endDate;
    }

    public function isActive(): bool
    {
        return $this->endDate === null;
    }
}
