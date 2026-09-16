<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Forumify\Core\Entity\IdentifiableEntityTrait;
use MajesticDev\CommandNet\Repository\ReportInRepository;

/**
 * A single muster/roll-call check-in. Kept as its own log (rather than only the
 * SoldierProfile::$lastReportIn column) so a report-in history exists at all - the column
 * is a denormalized cache of the latest one, kept for cheap roster/AWOL-detection reads
 * without a join or subquery per row.
 */
#[ORM\Entity(ReportInRepository::class)]
class ReportIn
{
    use IdentifiableEntityTrait;

    #[ORM\ManyToOne(targetEntity: SoldierProfile::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private SoldierProfile $soldier;

    #[ORM\Column(type: 'datetime')]
    private DateTime $reportedAt;

    public function __construct(SoldierProfile $soldier)
    {
        $this->soldier = $soldier;
        $this->reportedAt = new DateTime();
    }

    public function getSoldier(): SoldierProfile
    {
        return $this->soldier;
    }

    public function getReportedAt(): DateTime
    {
        return $this->reportedAt;
    }
}