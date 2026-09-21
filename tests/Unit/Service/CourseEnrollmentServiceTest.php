<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Tests\Unit\Service;

use DateTime;
use DomainException;
use Forumify\Core\Entity\User;
use MajesticDev\CommandNet\Entity\Course;
use MajesticDev\CommandNet\Entity\CourseClass;
use MajesticDev\CommandNet\Entity\CourseClassStudent;
use MajesticDev\CommandNet\Entity\Enum\SoldierStatus;
use MajesticDev\CommandNet\Entity\Rank;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Repository\CourseClassStudentRepository;
use MajesticDev\CommandNet\Service\CourseEnrollmentService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CourseEnrollmentServiceTest extends TestCase
{
    private CourseEnrollmentService $service;
    private CourseClassStudentRepository&MockObject $repository;

    /** @var array<Course> courses the soldier is treated as having passed */
    private array $passed = [];

    protected function setUp(): void
    {
        $this->repository = $this->createMock(CourseClassStudentRepository::class);
        $this->repository->method('hasPassed')
            ->willReturnCallback(fn (SoldierProfile $soldier, Course $course) => in_array($course, $this->passed, true));

        $this->service = new CourseEnrollmentService($this->repository);
    }

    public function testAnEnlistedSoldierCanEnrolInAFutureClassWithNoRequirements(): void
    {
        $this->assertNull($this->service->ineligibleReason($this->soldier(), $this->newClass()));
    }

    public function testDischargedSoldiersCannotEnrol(): void
    {
        $soldier = $this->soldier();
        $soldier->setStatus(SoldierStatus::DISCHARGED);

        $this->assertNotNull($this->service->ineligibleReason($soldier, $this->newClass()));
    }

    public function testAClassThatHasStartedOrBeenProcessedIsClosed(): void
    {
        $started = $this->newClass(startsAt: new DateTime('-1 hour'));
        $processed = $this->newClass();
        $processed->markProcessed();

        $this->assertStringContainsString('started', (string)$this->service->ineligibleReason($this->soldier(), $started));
        $this->assertStringContainsString('started', (string)$this->service->ineligibleReason($this->soldier(), $processed));
    }

    public function testCannotEnrolTwice(): void
    {
        $soldier = $this->soldier();
        $class = $this->newClass();
        $class->getStudents()->add(new CourseClassStudent($class, $soldier));

        $this->assertStringContainsString('already enrolled', (string)$this->service->ineligibleReason($soldier, $class));
    }

    public function testAFullClassIsClosed(): void
    {
        $class = $this->newClass();
        $class->setStudentSlots(1);
        $class->getStudents()->add(new CourseClassStudent($class, $this->soldier()));

        $this->assertStringContainsString('full', (string)$this->service->ineligibleReason($this->soldier(), $class));
    }

    public function testTheMinimumRankIsEnforced(): void
    {
        $sergeant = $this->rank('Sergeant', 3);
        $class = $this->newClass();
        $class->getCourse()->setMinimumRank($sergeant);

        $this->assertNotNull($this->service->ineligibleReason($this->soldier($this->rank('Private', 1)), $class));
        $this->assertNotNull($this->service->ineligibleReason($this->soldier(), $class), 'No rank at all is below any minimum.');
        $this->assertNull($this->service->ineligibleReason($this->soldier($this->rank('Sergeant', 3)), $class));
        $this->assertNull($this->service->ineligibleReason($this->soldier($this->rank('Captain', 5)), $class));
    }

    public function testEveryPrerequisiteMustHaveBeenPassed(): void
    {
        $basic = $this->course('Basic');
        $advanced = $this->course('Advanced');
        $class = $this->newClass(course: $this->course('Elite'));
        $class->getCourse()->addPrerequisite($basic);
        $class->getCourse()->addPrerequisite($advanced);

        $this->passed = [$basic];
        $this->assertStringContainsString('Advanced', (string)$this->service->ineligibleReason($this->soldier(), $class));

        $this->passed = [$basic, $advanced];
        $this->assertNull($this->service->ineligibleReason($this->soldier(), $class));
    }

    public function testEnrollAddsAStudentAndSavesIt(): void
    {
        $soldier = $this->soldier();
        $class = $this->newClass();

        $this->repository->expects($this->once())->method('save')->with($this->isInstanceOf(CourseClassStudent::class));

        $this->service->enroll($soldier, $class);

        $this->assertNotNull($class->getStudentFor($soldier));
    }

    public function testEnrollRefusesAnIneligibleSoldier(): void
    {
        $this->repository->expects($this->never())->method('save');
        $this->expectException(DomainException::class);

        $this->service->enroll($this->soldier(), $this->newClass(startsAt: new DateTime('-1 day')));
    }

    public function testWithdrawRemovesTheStudentBeforeTheClassStarts(): void
    {
        $soldier = $this->soldier();
        $class = $this->newClass();
        $this->service->enroll($soldier, $class);

        $this->repository->expects($this->once())->method('remove');

        $this->service->withdraw($soldier, $class);

        $this->assertNull($class->getStudentFor($soldier));
    }

    public function testCannotWithdrawOnceTheClassHasStarted(): void
    {
        $this->expectException(DomainException::class);

        $this->service->withdraw($this->soldier(), $this->newClass(startsAt: new DateTime('-1 hour')));
    }

    private function soldier(?Rank $rank = null): SoldierProfile
    {
        $soldier = new SoldierProfile(new User());
        $soldier->setRank($rank);

        return $soldier;
    }

    private function rank(string $name, int $position): Rank
    {
        $rank = new Rank();
        $rank->setName($name);
        $rank->setPosition($position);

        return $rank;
    }

    private function course(string $name): Course
    {
        $course = new Course();
        $course->setName($name);

        return $course;
    }

    private function newClass(?DateTime $startsAt = null, ?Course $course = null): CourseClass
    {
        $class = new CourseClass();
        $class->setCourse($course ?? $this->course('Basic'));
        $class->setStartsAt($startsAt ?? new DateTime('+1 week'));

        return $class;
    }
}
