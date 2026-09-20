<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Tests\Unit\Service;

use DateTime;
use Forumify\Core\Entity\User;
use Forumify\Core\Notification\NotificationService;
use Forumify\Core\Repository\RoleRepository;
use Forumify\Core\Repository\UserRepository;
use MajesticDev\CommandNet\Entity\Enum\ServiceRecordType;
use MajesticDev\CommandNet\Entity\Enum\SoldierStatus;
use MajesticDev\CommandNet\Entity\Operation;
use MajesticDev\CommandNet\Entity\OperationRSVP;
use MajesticDev\CommandNet\Entity\ServiceRecord;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Repository\OperationRSVPRepository;
use MajesticDev\CommandNet\Repository\ServiceRecordRepository;
use MajesticDev\CommandNet\Repository\SoldierProfileRepository;
use MajesticDev\CommandNet\Service\AttendanceCalculator;
use MajesticDev\CommandNet\Service\AwolService;
use MajesticDev\CommandNet\Service\AwolSettings;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class AwolServiceTest extends TestCase
{
    private AwolService $service;

    /** @var OperationRSVP[] attendance history the repository returns, newest first */
    private array $history = [];
    /** @var ServiceRecord[] */
    private array $saved = [];

    protected function setUp(): void
    {
        $settings = $this->createMock(AwolSettings::class);
        $settings->method('all')->willReturn(['enabled' => true, 'missThreshold' => 2, 'role' => null]);

        $rsvpRepository = $this->createMock(OperationRSVPRepository::class);
        $rsvpRepository->method('findAttendanceHistoryForUnit')->willReturnCallback(fn () => $this->history);

        $recordRepository = $this->createMock(ServiceRecordRepository::class);
        $recordRepository->method('save')->willReturnCallback(function (ServiceRecord $record): void {
            $this->saved[] = $record;
        });

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/roster/x');

        $this->service = new AwolService(
            $settings,
            new AttendanceCalculator($rsvpRepository),
            $rsvpRepository,
            $this->createMock(RoleRepository::class),
            $this->createMock(SoldierProfileRepository::class),
            $recordRepository,
            $this->createMock(UserRepository::class),
            $this->createMock(NotificationService::class),
            $urlGenerator,
        );
    }

    public function testFlagsActiveSoldierAndMarksItAsAutomatic(): void
    {
        $soldier = $this->soldier(SoldierStatus::ACTIVE);
        $this->setHistory($soldier, [false, false]);

        $this->service->checkAfterAttendanceChange($soldier);

        $this->assertSame(SoldierStatus::AWOL, $soldier->getStatus());
        $this->assertTrue($soldier->isAwolAutoFlagged());
        $this->assertSame([ServiceRecordType::AWOL], $this->savedTypes());
    }

    public function testDoesNotFlagBelowThreshold(): void
    {
        $soldier = $this->soldier(SoldierStatus::ACTIVE);
        $this->setHistory($soldier, [false, true]);

        $this->service->checkAfterAttendanceChange($soldier);

        $this->assertSame(SoldierStatus::ACTIVE, $soldier->getStatus());
        $this->assertSame([], $this->saved);
    }

    public function testClearsAnAutomaticAwolOnceTheSoldierAttends(): void
    {
        $soldier = $this->soldier(SoldierStatus::ACTIVE);
        $this->setHistory($soldier, [false, false]);
        $this->service->checkAfterAttendanceChange($soldier);
        $this->saved = [];

        $this->setHistory($soldier, [true, false, false]);
        $this->service->checkAfterAttendanceChange($soldier);

        $this->assertSame(SoldierStatus::ACTIVE, $soldier->getStatus());
        $this->assertFalse($soldier->isAwolAutoFlagged());
        $this->assertSame([ServiceRecordType::AWOL], $this->savedTypes());
    }

    public function testLeavesAnAdminSetAwolAlone(): void
    {
        $soldier = $this->soldier(SoldierStatus::AWOL);
        $this->setHistory($soldier, [true]);

        $this->service->checkAfterAttendanceChange($soldier);

        $this->assertSame(SoldierStatus::AWOL, $soldier->getStatus());
        $this->assertSame([], $this->saved);
    }

    public function testChangingStatusResetsTheAutomaticFlag(): void
    {
        $soldier = $this->soldier(SoldierStatus::AWOL);
        $soldier->setAwolAutoFlagged(true);

        $soldier->setStatus(SoldierStatus::LOA);
        $soldier->setStatus(SoldierStatus::AWOL);

        $this->assertFalse($soldier->isAwolAutoFlagged());
    }

    public function testDoesNotFlagSoldiersOnLoa(): void
    {
        $soldier = $this->soldier(SoldierStatus::LOA);
        $this->setHistory($soldier, [false, false, false]);

        $this->service->checkAfterAttendanceChange($soldier);

        $this->assertSame(SoldierStatus::LOA, $soldier->getStatus());
    }

    /**
     * @return ServiceRecordType[]
     */
    private function savedTypes(): array
    {
        return array_map(static fn (ServiceRecord $r) => $r->getType(), $this->saved);
    }

    private function soldier(SoldierStatus $status): SoldierProfile
    {
        $soldier = new SoldierProfile($this->createStub(User::class));
        $soldier->setStatus($status);

        return $soldier;
    }

    /**
     * @param bool[] $attended newest operation first
     */
    private function setHistory(SoldierProfile $soldier, array $attended): void
    {
        $this->history = array_map(static function (bool $didAttend) use ($soldier): OperationRSVP {
            $operation = new Operation();
            $operation->setStartDateTime(new DateTime());
            $rsvp = new OperationRSVP($operation, $soldier);
            $rsvp->setAttended($didAttend);

            return $rsvp;
        }, $attended);
    }
}
