<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Tests\Unit\Twig;

use Doctrine\ORM\EntityManagerInterface;
use Forumify\Core\Entity\User;
use MajesticDev\CommandNet\Entity\Enum\ServiceRecordType;
use MajesticDev\CommandNet\Entity\ServiceRecord;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Service\DocumentRenderer;
use MajesticDev\CommandNet\Service\RankSettings;
use MajesticDev\CommandNet\Twig\CommandNetRuntime;
use PHPUnit\Framework\TestCase;

class CommandNetRuntimeTest extends TestCase
{
    public function testServiceRecordsAreAllShownWhenRanksAreEnabled(): void
    {
        $soldier = $this->soldierWithRecords();

        $records = $this->runtime(true)->getServiceRecords($soldier);

        $this->assertSame(['Enlisted', 'Sergeant', 'Corporal', 'Northern Watch'], array_map(static fn (ServiceRecord $r) => $r->getTitle(), $records));
    }

    public function testPromotionsAndDemotionsAreLeftOutWhenRanksAreDisabled(): void
    {
        $soldier = $this->soldierWithRecords();

        $records = $this->runtime(false)->getServiceRecords($soldier);

        $this->assertSame(['Enlisted', 'Northern Watch'], array_map(static fn (ServiceRecord $r) => $r->getTitle(), $records), 'Enlistment and combat records are not about rank and stay.');
        $this->assertCount(4, $soldier->getServiceRecords(), 'Nothing is deleted, only left out of the list.');
    }

    public function testASoldierWithOnlyRankRecordsHasAnEmptyListWhenRanksAreDisabled(): void
    {
        $soldier = new SoldierProfile(new User());
        $soldier->getServiceRecords()->add(new ServiceRecord($soldier, ServiceRecordType::PROMOTION, 'Sergeant'));

        $this->assertSame([], $this->runtime(false)->getServiceRecords($soldier));
    }

    private function soldierWithRecords(): SoldierProfile
    {
        $soldier = new SoldierProfile(new User());
        foreach ([
            [ServiceRecordType::ENLISTMENT, 'Enlisted'],
            [ServiceRecordType::PROMOTION, 'Sergeant'],
            [ServiceRecordType::DEMOTION, 'Corporal'],
            [ServiceRecordType::COMBAT, 'Northern Watch'],
        ] as [$type, $title]) {
            $soldier->getServiceRecords()->add(new ServiceRecord($soldier, $type, $title));
        }

        return $soldier;
    }

    private function runtime(bool $ranksEnabled): CommandNetRuntime
    {
        $rankSettings = $this->createMock(RankSettings::class);
        $rankSettings->method('isEnabled')->willReturn($ranksEnabled);

        return new CommandNetRuntime(
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(DocumentRenderer::class),
            $rankSettings,
        );
    }
}
