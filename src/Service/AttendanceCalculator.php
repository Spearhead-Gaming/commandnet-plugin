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
        $missStreak = 0;
        $missStreakBroken = false;

        // History is newest-operation-first, so each streak is just "how many records of
        // that kind in a row before hitting the first record of the other kind" - the miss
        // streak (for AWOL detection) is the exact mirror of the existing attended streak.
        foreach ($history as $rsvp) {
            if ($rsvp->getAttended() === true) {
                ++$attended;
                if ($lastAttended === null) {
                    $lastAttended = $rsvp->getOperation()->getStartDateTime();
                }
                if (!$streakBroken) {
                    ++$streak;
                }
                $missStreakBroken = true;
            } else {
                ++$noShows;
                $streakBroken = true;
                if (!$missStreakBroken) {
                    ++$missStreak;
                }
            }
        }

        $total = $attended + $noShows;
        $noShowRate = $total > 0 ? round($noShows / $total * 100, 1) : null;

        return new AttendanceStats($attended, $noShows, $noShowRate, $lastAttended, $streak, $missStreak);
    }
}