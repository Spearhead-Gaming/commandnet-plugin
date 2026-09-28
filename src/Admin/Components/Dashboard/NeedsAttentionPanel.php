<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Admin\Components\Dashboard;

use MajesticDev\CommandNet\Entity\Enum\ApplicationStatus;
use MajesticDev\CommandNet\Entity\Enum\SoldierStatus;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Entity\Unit;
use MajesticDev\CommandNet\Repository\EnlistmentApplicationRepository;
use MajesticDev\CommandNet\Repository\SoldierProfileRepository;
use MajesticDev\CommandNet\Repository\UnitRepository;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * The overview page's "what needs a decision" panel: pending enlistment applications, the
 * AWOL roster by name, and units with no commander assigned - in that priority order, since
 * that's roughly how urgently each one needs an admin's attention.
 */
#[AsTwigComponent('CommandNet\\Admin\\NeedsAttentionPanel', '@CommandNetPlugin/admin/dashboard/needs_attention.html.twig')]
class NeedsAttentionPanel
{
    public function __construct(
        private readonly EnlistmentApplicationRepository $enlistmentApplicationRepository,
        private readonly SoldierProfileRepository $soldierProfileRepository,
        private readonly UnitRepository $unitRepository,
    ) {
    }

    public function getPendingApplicationCount(): int
    {
        return $this->enlistmentApplicationRepository->count(['status' => ApplicationStatus::PENDING]);
    }

    /**
     * @return array<SoldierProfile>
     */
    public function getAwolSoldiers(): array
    {
        $soldiers = $this->soldierProfileRepository->findBy(['status' => SoldierStatus::AWOL]);
        usort($soldiers, static fn (SoldierProfile $a, SoldierProfile $b): int => (string)$a <=> (string)$b);

        return $soldiers;
    }

    /**
     * Units with nobody in command. Squads don't carry a commander field of their own
     * (see HasPositions), so this is Unit-only.
     *
     * @return array<Unit>
     */
    public function getUnfilledCommandUnits(): array
    {
        $units = $this->unitRepository->findBy(['commander' => null]);
        usort($units, static fn (Unit $a, Unit $b): int => (string)$a <=> (string)$b);

        return $units;
    }
}
