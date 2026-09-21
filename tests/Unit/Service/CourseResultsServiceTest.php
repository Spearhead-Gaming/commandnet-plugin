<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Tests\Unit\Service;

use DateTime;
use DomainException;
use Forumify\Core\Entity\User;
use Forumify\Core\Notification\NotificationService;
use MajesticDev\CommandNet\Entity\Course;
use MajesticDev\CommandNet\Entity\CourseClass;
use MajesticDev\CommandNet\Entity\CourseClassStudent;
use MajesticDev\CommandNet\Entity\Enum\CourseResult;
use MajesticDev\CommandNet\Entity\Enum\ServiceRecordType;
use MajesticDev\CommandNet\Entity\Qualification;
use MajesticDev\CommandNet\Entity\ServiceRecord;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Entity\SoldierQualification;
use MajesticDev\CommandNet\Repository\CourseClassRepository;
use MajesticDev\CommandNet\Repository\ServiceRecordRepository;
use MajesticDev\CommandNet\Repository\SoldierQualificationRepository;
use MajesticDev\CommandNet\Service\CourseResultsService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class CourseResultsServiceTest extends TestCase
{
    private CourseResultsService $service;
    private NotificationService&MockObject $notificationService;
    private CourseClassRepository&MockObject $classRepository;

    /** @var ServiceRecord[] */
    private array $records = [];
    /** @var SoldierQualification[] */
    private array $granted = [];
    private int $nextId = 100;

    protected function setUp(): void
    {
        $qualificationRepository = $this->createMock(SoldierQualificationRepository::class);
        $qualificationRepository->method('save')->willReturnCallback(function (SoldierQualification $q): void {
            $this->setId($q, $this->nextId++);
            $this->granted[] = $q;
        });
        $recordRepository = $this->createMock(ServiceRecordRepository::class);
        $recordRepository->method('save')->willReturnCallback(function (ServiceRecord $record): void {
            $this->records[] = $record;
        });
        $this->classRepository = $this->createMock(CourseClassRepository::class);
        $this->notificationService = $this->createMock(NotificationService::class);
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/courses');

        $this->service = new CourseResultsService(
            $qualificationRepository,
            $recordRepository,
            $this->classRepository,
            $this->notificationService,
            $urlGenerator,
        );
    }

    public function testAPassWritesACourseRecordAndGrantsTheQualifications(): void
    {
        $qualification = $this->qualification(1, 'Marksman');
        $class = $this->newClass($qualification);
        $passer = $this->enrol($class, 10);
        $failer = $this->enrol($class, 11);

        $this->classRepository->expects($this->once())->method('save')->with($class);
        $this->notificationService->expects($this->exactly(2))->method('sendNotification');

        $this->service->process($class, [10 => CourseResult::PASSED, 11 => CourseResult::FAILED]);

        $this->assertTrue($class->isProcessed());
        $this->assertSame(CourseResult::PASSED, $passer->getResult());
        $this->assertSame(CourseResult::FAILED, $failer->getResult());

        $this->assertCount(1, $this->granted);
        $this->assertSame($passer->getSoldier(), $this->granted[0]->getSoldier());
        $this->assertSame($qualification, $this->granted[0]->getQualification());

        $this->assertSame(
            [ServiceRecordType::COURSE, ServiceRecordType::QUALIFICATION],
            array_map(static fn (ServiceRecord $r) => $r->getType(), $this->records),
        );
        $this->assertSame('Passed Rifle Course', $this->records[0]->getTitle());
        $this->assertSame(ServiceRecord::SOURCE_QUALIFICATION, $this->records[1]->getSourceType());
        $this->assertSame($this->granted[0]->getId(), $this->records[1]->getSourceId());
    }

    public function testAQualificationTheSoldierAlreadyHoldsIsNotGrantedAgain(): void
    {
        $qualification = $this->qualification(1, 'Marksman');
        $class = $this->newClass($qualification);
        $student = $this->enrol($class, 10);
        $student->getSoldier()->getQualifications()->add(new SoldierQualification($student->getSoldier(), $qualification));

        $this->service->process($class, [10 => CourseResult::PASSED]);

        $this->assertSame([], $this->granted);
        $this->assertSame([ServiceRecordType::COURSE], array_map(static fn (ServiceRecord $r) => $r->getType(), $this->records));
    }

    public function testNoShowsAndExcusedGetNoRecordsOrQualifications(): void
    {
        $class = $this->newClass($this->qualification(1, 'Marksman'));
        $this->enrol($class, 10);
        $this->enrol($class, 11);

        $this->service->process($class, [10 => CourseResult::NO_SHOW, 11 => CourseResult::EXCUSED]);

        $this->assertSame([], $this->records);
        $this->assertSame([], $this->granted);
    }

    public function testEveryStudentNeedsAResult(): void
    {
        $class = $this->newClass();
        $this->enrol($class, 10);
        $this->enrol($class, 11);

        $this->classRepository->expects($this->never())->method('save');
        $this->expectException(DomainException::class);

        $this->service->process($class, [10 => CourseResult::PASSED]);
    }

    public function testResultsCanOnlyBeRecordedOnce(): void
    {
        $class = $this->newClass();
        $this->enrol($class, 10);
        $this->service->process($class, [10 => CourseResult::FAILED]);

        $this->expectException(DomainException::class);
        $this->service->process($class, [10 => CourseResult::PASSED]);
    }

    public function testResultsCannotBeRecordedBeforeTheClassStarts(): void
    {
        $class = $this->newClass();
        $class->setStartsAt(new DateTime('+1 day'));
        $this->enrol($class, 10);

        $this->expectException(DomainException::class);
        $this->service->process($class, [10 => CourseResult::PASSED]);
    }

    private function newClass(?Qualification $grants = null): CourseClass
    {
        $course = new Course();
        $course->setName('Rifle Course');
        if ($grants !== null) {
            $course->addQualification($grants);
        }

        $class = new CourseClass();
        $class->setCourse($course);
        $class->setStartsAt(new DateTime('-1 hour'));
        $this->setId($class, 1);

        return $class;
    }

    private function enrol(CourseClass $class, int $studentId): CourseClassStudent
    {
        $user = new User();
        $user->setUsername('user' . $studentId);
        $student = new CourseClassStudent($class, new SoldierProfile($user));
        $this->setId($student, $studentId);
        $class->getStudents()->add($student);

        return $student;
    }

    private function qualification(int $id, string $name): Qualification
    {
        $qualification = new Qualification();
        $qualification->setName($name);
        $this->setId($qualification, $id);

        return $qualification;
    }

    private function setId(object $entity, int $id): void
    {
        (new ReflectionProperty($entity, 'id'))->setValue($entity, $id);
    }
}
