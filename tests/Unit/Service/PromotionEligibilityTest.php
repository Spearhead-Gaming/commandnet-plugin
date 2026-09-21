<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Tests\Unit\Service;

use Forumify\Core\Entity\User;
use MajesticDev\CommandNet\Entity\Rank;
use MajesticDev\CommandNet\Entity\RankGroup;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Repository\RankRepository;
use MajesticDev\CommandNet\Repository\SoldierProfileRepository;
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

    public function testSoldierWithoutARankHasNoNextRank(): void
    {
        $service = $this->service([$this->rank(1, 1)]);

        $this->assertNull($service->evaluateSoldier(new SoldierProfile(new User())));
    }

    /**
     * @param array<Rank> $ranks ordered by position, as the repository returns them
     */
    private function service(array $ranks): PromotionEligibility
    {
        $rankRepository = $this->createMock(RankRepository::class);
        $rankRepository->method('findBy')->willReturn($ranks);

        return new PromotionEligibility($rankRepository, $this->createMock(SoldierProfileRepository::class));
    }

    private function soldierAt(Rank $rank): SoldierProfile
    {
        $soldier = new SoldierProfile(new User());
        $soldier->setRank($rank);

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
