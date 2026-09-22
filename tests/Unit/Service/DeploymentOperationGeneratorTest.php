<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Tests\Unit\Service;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use MajesticDev\CommandNet\Entity\Deployment;
use MajesticDev\CommandNet\Entity\Enum\OperationType;
use MajesticDev\CommandNet\Entity\Operation;
use MajesticDev\CommandNet\Repository\OperationRepository;
use MajesticDev\CommandNet\Service\DeploymentOperationGenerator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DeploymentOperationGeneratorTest extends TestCase
{
    private OperationRepository&MockObject $repository;
    private DeploymentOperationGenerator $generator;
    private string $previousTimezone;

    protected function setUp(): void
    {
        // Operation times are stored in PHP's default timezone; pin it so the UTC maths below is stable.
        $this->previousTimezone = date_default_timezone_get();
        date_default_timezone_set('UTC');

        $this->repository = $this->createMock(OperationRepository::class);
        $this->generator = new DeploymentOperationGenerator($this->repository);
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->previousTimezone);
    }

    public function testCreatesAnOperationEveryWednesdayAndSaturdayAt2000Eastern(): void
    {
        // October 2026: Wednesdays 7, 14, 21, 28 and Saturdays 3, 10, 17, 24, 31.
        $slots = $this->generator->slots($this->deployment('2026-10-01', '2026-10-31'));

        $this->assertSame(
            ['10-03', '10-07', '10-10', '10-14', '10-17', '10-21', '10-24', '10-28', '10-31'],
            array_map(fn ($slot) => $this->eastern($slot)->format('m-d'), $slots),
        );
        foreach ($slots as $slot) {
            $this->assertSame('20:00', $this->eastern($slot)->format('H:i'));
            $this->assertContains($this->eastern($slot)->format('D'), ['Wed', 'Sat']);
        }
    }

    public function testIncludesTheFirstAndLastDay(): void
    {
        $slots = $this->generator->slots($this->deployment('2026-10-03', '2026-10-07'));

        $this->assertSame(['10-03', '10-07'], array_map(fn ($slot) => $this->eastern($slot)->format('m-d'), $slots));
    }

    public function testFollowsDaylightSaving(): void
    {
        // Daylight time ends on 2026-11-01: 2000 Eastern is 00:00 UTC before it and 01:00 UTC after.
        $october = $this->generator->slots($this->deployment('2026-10-07', '2026-10-07'))[0];
        $november = $this->generator->slots($this->deployment('2026-11-04', '2026-11-04'))[0];

        $this->assertSame('2026-10-08 00:00', $october->format('Y-m-d H:i'));
        $this->assertSame('2026-11-05 01:00', $november->format('Y-m-d H:i'));
    }

    public function testBuildsUnsavedOperationsBelongingToTheDeployment(): void
    {
        $this->repository->method('findBy')->willReturn([]);
        $deployment = $this->deployment('2026-10-07', '2026-10-07', 'October 2026');

        $operations = $this->generator->build($deployment);

        $this->assertCount(1, $operations);
        $this->assertSame(OperationType::OPERATION, $operations[0]->getType());
        $this->assertSame($deployment, $operations[0]->getDeployment());
        $this->assertSame('October 2026 - Wednesday Oct 7', $operations[0]->getTitle());
        $this->assertSame('2026-10-08 00:00', $operations[0]->getStartDateTime()->format('Y-m-d H:i'));
        $this->assertSame('2026-10-08 03:00', $operations[0]->getEndDateTime()?->format('Y-m-d H:i'));
    }

    public function testSlotsThatAlreadyHaveAnOperationAreSkipped(): void
    {
        $existing = new Operation();
        $existing->setStartDateTime(new DateTime('2026-10-08 00:00')); // Wed Oct 7, 2000 Eastern
        $this->repository->method('findBy')->willReturn([$existing]);

        $operations = $this->generator->build($this->deployment('2026-10-03', '2026-10-10'));

        $this->assertSame(['2026-10-04 00:00', '2026-10-11 00:00'], array_map(
            static fn (Operation $o) => $o->getStartDateTime()->format('Y-m-d H:i'),
            $operations,
        ));
    }

    public function testGenerateSavesEachNewOperationAndFlushesOnce(): void
    {
        $this->repository->method('findBy')->willReturn([]);
        $this->repository->expects($this->exactly(2))->method('save');
        $this->repository->expects($this->once())->method('flush');

        $this->assertSame(2, $this->generator->generate($this->deployment('2026-10-03', '2026-10-07')));
    }

    private function eastern(DateTimeInterface $slot): DateTimeImmutable
    {
        return DateTimeImmutable::createFromInterface($slot)->setTimezone(new DateTimeZone('America/New_York'));
    }

    private function deployment(string $start, string $end, string $name = 'Deployment'): Deployment
    {
        $deployment = new Deployment();
        $deployment->setName($name);
        $deployment->setStartDate(new DateTime($start));
        $deployment->setEndDate(new DateTime($end));

        return $deployment;
    }
}
