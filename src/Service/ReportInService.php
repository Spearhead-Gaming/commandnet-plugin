<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Service;

use DateTimeImmutable;
use MajesticDev\CommandNet\Entity\Enum\SoldierStatus;
use MajesticDev\CommandNet\Entity\ReportIn;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Repository\ReportInRepository;
use MajesticDev\CommandNet\Repository\SoldierProfileRepository;

/**
 * Enforces the Report In requirement, mirroring MILHQ's daily check: an active soldier who
 * has not reported in within the period is flagged AWOL (reusing AwolService for the role,
 * audit record and notification), one nearing the deadline gets a warning, and reporting in
 * again restores Active. A soldier with no report in on file gets a baseline entry instead of
 * being failed, so switching the feature on does not flag the whole roster at once.
 */
class ReportInService
{
    public function __construct(
        private readonly ReportInSettings $settings,
        private readonly SoldierProfileRepository $soldierProfileRepository,
        private readonly ReportInRepository $reportInRepository,
        private readonly AwolService $awolService,
    ) {
    }

    public function runChecks(?DateTimeImmutable $now = null): void
    {
        $settings = $this->settings->all();
        if (!$settings['enabled']) {
            return;
        }

        $now ??= new DateTimeImmutable();
        $period = (int)$settings['periodDays'];
        $warningDays = (int)$settings['warningDays'];

        foreach ($this->soldierProfileRepository->findBy(['status' => SoldierStatus::ACTIVE]) as $soldier) {
            $last = $soldier->getLastReportIn();
            if ($last === null) {
                $this->recordReportIn($soldier);
                continue;
            }

            $daysSince = (int)DateTimeImmutable::createFromInterface($last)->diff($now)->days;
            if ($daysSince > $period) {
                $this->awolService->flagAwol(
                    $soldier,
                    "Flagged AWOL for failing to report in within $period days.",
                    "You have not reported in for over $period days and have been marked AWOL. Report in to return to Active.",
                    byReportIn: true,
                );
                continue;
            }

            $daysLeft = $period - $daysSince + 1;
            if ($warningDays > 0 && $daysLeft <= $warningDays) {
                $this->awolService->notify(
                    $soldier,
                    'Report in soon',
                    "You have $daysLeft day(s) left to report in before being marked AWOL.",
                );
            }
        }
    }

    /**
     * Called after a soldier reports in: undoes an AWOL that came from a missed report in.
     * An AWOL from attendance or set by an admin is left for its own rules to clear.
     */
    public function handleReportedIn(SoldierProfile $soldier): void
    {
        if ($soldier->getStatus() === SoldierStatus::AWOL && $soldier->isReportInFlagged()) {
            $this->awolService->clearAwol(
                $soldier,
                'Returned to Active status after reporting in.',
                'Thanks for reporting in - your status has been reverted to Active.',
            );
        }
    }

    private function recordReportIn(SoldierProfile $soldier): void
    {
        $reportIn = new ReportIn($soldier);
        $this->reportInRepository->save($reportIn);
        $soldier->setLastReportIn($reportIn->getReportedAt());
        $this->soldierProfileRepository->save($soldier);
    }
}
