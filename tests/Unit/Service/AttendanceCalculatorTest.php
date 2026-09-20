<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Tests\Unit\Service;

use DateTime;
use Forumify\Core\Entity\User;
use MajesticDev\CommandNet\Entity\Operation;
use MajesticDev\CommandNet\Entity\OperationRSVP;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Repository\OperationRSVPRepository;
use MajesticDev\CommandNet\Service\AttendanceCalculator;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class AttendanceCalculatorTest extends TestCase
{
    public function testCalculateSummarisesASoldiersHistory(): void
    {
        $soldier = $this->soldier(1);
        $repository = $this->createMock(OperationRSVPRepository::class);
        $repository->method('findAttendanceHistory')
            ->willReturn($this->history($soldier, [true, true, false, true]));

        $stats = (new AttendanceCalculator($repository))->calculate($soldier);

        $this->assertSame(3, $stats->attended);
        $this->assertSame(1, $stats->noShows);
        $this->assertSame(25.0, $stats->noShowRate);
        $this->assertSame(2, $stats->currentStreak);
        $this->assertSame(0, $stats->currentMissStreak);
    }

    public function testCalculateManyUsesOneQueryAndMatchesPerSoldierResults(): void
    {
        $reliable = $this->soldier(1);
        $flaky = $this->soldier(2);
        $newcomer = $this->soldier(3);

        $repository = $this->createMock(OperationRSVPRepository::class);
        $repository->expects($this->once())->method('findAttendanceHistoryForSoldiers')
            ->with([$reliable, $flaky, $newcomer])
            ->willReturn([
                1 => $this->history($reliable, [true, true]),
                2 => $this->history($flaky, [false, false, true]),
            ]);
        $repository->expects($this->never())->method('findAttendanceHistory');

        $stats = (new AttendanceCalculator($repository))->calculateMany([$reliable, $flaky, $newcomer]);

        $this->assertSame([1, 2, 3], array_keys($stats));
        $this->assertSame(2, $stats[1]->attended);
        $this->assertSame(2, $stats[1]->currentStreak);
        $this->assertSame(1, $stats[2]->attended);
        $this->assertSame(2, $stats[2]->noShows);
        $this->assertSame(2, $stats[2]->currentMissStreak);
        $this->assertFalse($stats[3]->hasHistory(), 'A soldier with no history still gets an empty entry.');
    }

    /**
     * @param array<bool> $attended newest operation first
     * @return array<OperationRSVP>
     */
    private function history(SoldierProfile $soldier, array $attended): array
    {
        return array_map(static function (bool $didAttend) use ($soldier): OperationRSVP {
            $operation = new Operation();
            $operation->setStartDateTime(new DateTime());
            $rsvp = new OperationRSVP($operation, $soldier);
            $rsvp->setAttended($didAttend);

            return $rsvp;
        }, $attended);
    }

    private function soldier(int $id): SoldierProfile
    {
        $soldier = new SoldierProfile(new User());
        (new ReflectionProperty($soldier, 'id'))->setValue($soldier, $id);

        return $soldier;
    }
}
