<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Tests\Unit\Service;

use DateTimeImmutable;
use Forumify\Core\Entity\User;
use MajesticDev\CommandNet\Entity\Qualification;
use MajesticDev\CommandNet\Entity\Rank;
use MajesticDev\CommandNet\Entity\RankGroup;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Repository\RankRepository;
use MajesticDev\CommandNet\Repository\ServiceRecordRepository;
use MajesticDev\CommandNet\Repository\SoldierProfileRepository;
use MajesticDev\CommandNet\Repository\SoldierQualificationRepository;
use MajesticDev\CommandNet\Service\PromotionEligibility;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class PromotionEligibilityTest extends TestCase
{
    public function testRanksWithoutAGroupShareOneLadder(): void
    {
        $private = $this->rank(1, 1);
        $corporal = $this->rank(2, 2);

        $evaluation = $this->service([$private, $corporal])->evaluateSoldier($this->soldierAt($private));

        $this->assertSame($corporal, $evaluation['nextRank'] ?? null);
    }

    public function testTopOfAGroupHasNoNextRank(): void
    {
        $enlisted = $this->group(1);
        $officers = $this->group(2);
        $sergeant = $this->rank(1, 1, $enlisted);
        $topEnlisted = $this->rank(2, 2, $enlisted);
        $lieutenant = $this->rank(3, 3, $officers);

        $service = $this->service([$sergeant, $topEnlisted, $lieutenant]);

        $this->assertNull($service->evaluateSoldier($this->soldierAt($topEnlisted)));
        $this->assertNull($service->evaluateSoldier($this->soldierAt($lieutenant)));
        $this->assertSame($topEnlisted, $service->evaluateSoldier($this->soldierAt($sergeant))['nextRank'] ?? null);
    }

    public function testNextRankSkipsOverRanksInOtherGroups(): void
    {
        $enlisted = $this->group(1);
        $officers = $this->group(2);
        $first = $this->rank(1, 1, $enlisted);
        $officer = $this->rank(2, 2, $officers);
        $second = $this->rank(3, 3, $enlisted);

        $evaluation = $this->service([$first, $officer, $second])->evaluateSoldier($this->soldierAt($first));

        $this->assertSame($second, $evaluation['nextRank'] ?? null);
    }

    public function testRosterLoadsQualificationsAndRankChangesOncePerRosterNotPerSoldier(): void
    {
        $private = $this->rank(1, 1);
        $corporal = $this->rank(2, 2);
        $corporal->setMinTimeInGradeDays(10);
        $qualification = new Qualification();
        $this->setId($qualification, 50);
        $corporal->getRequiredQualifications()->add($qualification);

        $ready = $this->soldierAt($private, 1);
        $tooRecent = $this->soldierAt($private, 2);
        $unqualified = $this->soldierAt($private, 3);

        $soldierRepository = $this->createMock(SoldierProfileRepository::class);
        $soldierRepository->method('findRoster')->willReturn([$ready, $tooRecent, $unqualified]);
        $qualificationRepository = $this->createMock(SoldierQualificationRepository::class);
        $qualificationRepository->expects($this->once())->method('findHeldQualificationIds')
            ->with([$ready, $tooRecent, $unqualified])
            ->willReturn([1 => [50 => true], 2 => [50 => true]]);
        $recordRepository = $this->createMock(ServiceRecordRepository::class);
        $recordRepository->expects($this->once())->method('findLatestRankChangeDates')
            ->willReturn([
                1 => new DateTimeImmutable('-20 days'),
                2 => new DateTimeImmutable('-3 days'),
                3 => new DateTimeImmutable('-20 days'),
            ]);

        $rows = $this->service([$private, $corporal], $soldierRepository, $qualificationRepository, $recordRepository)
            ->evaluateRoster();

        $this->assertSame([true, false, false], array_column($rows, 'eligible'));
        $this->assertSame([0, 7, 0], array_column($rows, 'missingDays'));
        $this->assertSame([[], [], [$qualification]], array_column($rows, 'missingQualifications'));
    }

    public function testRosterWithNobodyToPromoteReturnsNoRows(): void
    {
        $soldierRepository = $this->createMock(SoldierProfileRepository::class);
        $soldierRepository->method('findRoster')->willReturn([]);
        $qualificationRepository = $this->createMock(SoldierQualificationRepository::class);
        $qualificationRepository->method('findHeldQualificationIds')->willReturn([]);

        $this->assertSame([], $this->service([$this->rank(1, 1)], $soldierRepository, $qualificationRepository)->evaluateRoster());
    }

    public function testSoldierWithoutARankHasNoNextRank(): void
    {
        $service = $this->service([$this->rank(1, 1)]);

        $this->assertNull($service->evaluateSoldier(new SoldierProfile(new User())));
    }

    /**
     * @param array<Rank> $ranks ordered by position, as the repository returns them
     */
    private function service(
        array $ranks,
        ?SoldierProfileRepository $soldierRepository = null,
        ?SoldierQualificationRepository $qualificationRepository = null,
        ?ServiceRecordRepository $recordRepository = null,
    ): PromotionEligibility
    {
        $rankRepository = $this->createMock(RankRepository::class);
        $rankRepository->method('findAllWithRequirements')->willReturn($ranks);

        return new PromotionEligibility(
            $rankRepository,
            $soldierRepository ?? $this->createMock(SoldierProfileRepository::class),
            $qualificationRepository ?? $this->createMock(SoldierQualificationRepository::class),
            $recordRepository ?? $this->createMock(ServiceRecordRepository::class),
        );
    }

    private function soldierAt(Rank $rank, ?int $id = null): SoldierProfile
    {
        $soldier = new SoldierProfile(new User());
        $soldier->setRank($rank);
        if ($id !== null) {
            $this->setId($soldier, $id);
        }

        return $soldier;
    }

    private function rank(int $id, int $position, ?RankGroup $group = null): Rank
    {
        $rank = new Rank();
        $rank->setName('Rank ' . $id);
        $rank->setPosition($position);
        $rank->setGroup($group);
        $this->setId($rank, $id);

        return $rank;
    }

    private function group(int $id): RankGroup
    {
        $group = new RankGroup();
        $group->setName('Group ' . $id);
        $this->setId($group, $id);

        return $group;
    }

    private function setId(object $entity, int $id): void
    {
        (new ReflectionProperty($entity, 'id'))->setValue($entity, $id);
    }
}
