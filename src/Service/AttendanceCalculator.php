<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Service;

use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Repository\OperationRSVPRepository;

class AttendanceCalculator
{
    public function __construct(private readonly OperationRSVPRepository $rsvpRepository)
    {
    }

    public function calculate(SoldierProfile $soldier): AttendanceStats
    {
        $history = $this->rsvpRepository->findAttendanceHistory($soldier);

        $attended = 0;
        $noShows = 0;
        $lastAttended = null;
        $streak = 0;
        $streakBroken = false;

        // History is newest-operation-first, so the streak is just "how many attended
        // records in a row before hitting the first no-show".
        foreach ($history as $rsvp) {
            if ($rsvp->getAttended() === true) {
                ++$attended;
                if ($lastAttended === null) {
                    $lastAttended = $rsvp->getOperation()->getStartDateTime();
                }
                if (!$streakBroken) {
                    ++$streak;
                }
            } else {
                ++$noShows;
                $streakBroken = true;
            }
        }

        $total = $attended + $noShows;
        $noShowRate = $total > 0 ? round($noShows / $total * 100, 1) : null;

        return new AttendanceStats($attended, $noShows, $noShowRate, $lastAttended, $streak, $this->missStreakFrom($history));
    }

    /**
     * Current consecutive-miss streak for an arbitrary (newest-first) attendance history -
     * exposed separately from calculate() so AWOL detection can scope the history to a
     * soldier's current unit rather than their entire attendance record.
     *
     * @param \MajesticDev\CommandNet\Entity\OperationRSVP[] $history
     */
    public function missStreakFrom(array $history): int
    {
        $missStreak = 0;
        foreach ($history as $rsvp) {
            if ($rsvp->getAttended() === true) {
                break;
            }
            ++$missStreak;
        }
        return $missStreak;
    }
}