<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Tests\Unit\Service;

use Forumify\Core\Entity\User;
use Forumify\Core\Notification\NotificationService;
use MajesticDev\CommandNet\Entity\Enum\ServiceRecordType;
use MajesticDev\CommandNet\Entity\Rank;
use MajesticDev\CommandNet\Entity\ServiceRecord;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Repository\ServiceRecordRepository;
use MajesticDev\CommandNet\Repository\SoldierProfileRepository;
use MajesticDev\CommandNet\Service\RankChangeService;
use MajesticDev\CommandNet\Service\RankRoleSyncer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class RankChangeServiceTest extends TestCase
{
    private SoldierProfileRepository&MockObject $soldierRepository;
    private RankRoleSyncer&MockObject $roleSyncer;
    private NotificationService&MockObject $notificationService;
    private RankChangeService $service;

    /** @var ServiceRecord[] */
    private array $saved = [];

    protected function setUp(): void
    {
        $this->soldierRepository = $this->createMock(SoldierProfileRepository::class);
        $recordRepository = $this->createMock(ServiceRecordRepository::class);
        $recordRepository->method('save')->willReturnCallback(function (ServiceRecord $record): void {
            $this->saved[] = $record;
        });
        $this->roleSyncer = $this->createMock(RankRoleSyncer::class);
        $this->notificationService = $this->createMock(NotificationService::class);
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/roster/x');

        $this->service = new RankChangeService(
            $this->soldierRepository,
            $recordRepository,
            $this->roleSyncer,
            $this->notificationService,
            $urlGenerator,
        );
    }

    public function testPromotionSetsRankWritesRecordSyncsRoleAndNotifies(): void
    {
        $soldier = $this->soldier($this->rank(1));
        $sergeant = $this->rank(2);

        $this->soldierRepository->expects($this->once())->method('save')->with($soldier);
        $this->roleSyncer->expects($this->once())->method('sync')->with($soldier);
        $this->notificationService->expects($this->once())->method('sendNotification');

        $this->service->changeRank($soldier, $sergeant);

        $this->assertSame($sergeant, $soldier->getRank());
        $this->assertSame([ServiceRecordType::PROMOTION], $this->savedTypes());
    }

    public function testMovingToALowerRankIsADemotion(): void
    {
        $soldier = $this->soldier($this->rank(2));

        $this->service->changeRank($soldier, $this->rank(1));

        $this->assertSame([ServiceRecordType::DEMOTION], $this->savedTypes());
    }

    public function testFirstEverRankCountsAsAPromotion(): void
    {
        $soldier = $this->soldier(null);

        $this->service->changeRank($soldier, $this->rank(1));

        $this->assertSame([ServiceRecordType::PROMOTION], $this->savedTypes());
    }

    public function testUnchangedRankDoesNothing(): void
    {
        $rank = $this->rank(1);
        $soldier = $this->soldier($rank);

        $this->roleSyncer->expects($this->never())->method('sync');
        $this->notificationService->expects($this->never())->method('sendNotification');

        $this->service->afterRankChange($soldier, $rank);

        $this->assertSame([], $this->saved);
    }

    public function testClearedRankSyncsRolesButWritesNoRecordOrNotification(): void
    {
        $soldier = $this->soldier(null);

        $this->roleSyncer->expects($this->once())->method('sync')->with($soldier);
        $this->notificationService->expects($this->never())->method('sendNotification');

        $this->service->afterRankChange($soldier, $this->rank(2));

        $this->assertSame([], $this->saved);
    }

    /**
     * @return ServiceRecordType[]
     */
    private function savedTypes(): array
    {
        return array_map(static fn (ServiceRecord $r) => $r->getType(), $this->saved);
    }

    private function soldier(?Rank $rank): SoldierProfile
    {
        $user = new User();
        $user->setUsername('soldier');
        $soldier = new SoldierProfile($user);
        $soldier->setRank($rank);

        return $soldier;
    }

    private function rank(int $position): Rank
    {
        $rank = new Rank();
        $rank->setName('Rank ' . $position);
        $rank->setPosition($position);

        return $rank;
    }
}
