<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Service;

use DomainException;
use Forumify\Core\Entity\Notification;
use Forumify\Core\Notification\GenericNotificationType;
use Forumify\Core\Notification\NotificationService;
use MajesticDev\CommandNet\Entity\CourseClass;
use MajesticDev\CommandNet\Entity\CourseClassStudent;
use MajesticDev\CommandNet\Entity\Enum\CourseResult;
use MajesticDev\CommandNet\Entity\Enum\ServiceRecordType;
use MajesticDev\CommandNet\Entity\ServiceRecord;
use MajesticDev\CommandNet\Entity\SoldierQualification;
use MajesticDev\CommandNet\Repository\CourseClassRepository;
use MajesticDev\CommandNet\Repository\ServiceRecordRepository;
use MajesticDev\CommandNet\Repository\SoldierQualificationRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Closing out a class: every student gets a result, each pass writes a course record and grants
 * the qualifications the course gives (skipping ones already held), and everyone is told.
 * A class is processed once; a mistake is fixed by removing the entries it wrote.
 */
class CourseResultsService
{
    public function __construct(
        private readonly SoldierQualificationRepository $qualificationRepository,
        private readonly ServiceRecordRepository $serviceRecordRepository,
        private readonly CourseClassRepository $classRepository,
        private readonly NotificationService $notificationService,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * @param array<int, CourseResult|null> $results student id => result, one for every student
     */
    public function process(CourseClass $class, array $results): void
    {
        if ($class->isProcessed()) {
            throw new DomainException('The results of this class have already been recorded.');
        }
        if (!$class->hasStarted()) {
            throw new DomainException('Results can be recorded once the class has started.');
        }

        foreach ($class->getStudents() as $student) {
            $result = $results[$student->getId()] ?? null;
            if ($result === null) {
                throw new DomainException('Choose a result for every student.');
            }
            $student->setResult($result);
        }

        foreach ($class->getStudents() as $student) {
            if ($student->getResult() === CourseResult::PASSED) {
                $this->grant($class, $student);
            }
            $this->notify($class, $student);
        }

        $class->markProcessed();
        $this->classRepository->save($class);
    }

    private function grant(CourseClass $class, CourseClassStudent $student): void
    {
        $soldier = $student->getSoldier();
        $course = $class->getCourse();

        $record = new ServiceRecord($soldier, ServiceRecordType::COURSE, 'Passed ' . $course->getName());
        $record->setDate($class->getStartsAt());
        $this->serviceRecordRepository->save($record);

        $held = [];
        foreach ($soldier->getQualifications() as $soldierQualification) {
            $held[$soldierQualification->getQualification()->getId()] = true;
        }

        foreach ($course->getQualifications() as $qualification) {
            if (isset($held[$qualification->getId()])) {
                continue;
            }

            $granted = new SoldierQualification($soldier, $qualification);
            $granted->setDateEarned($class->getStartsAt());
            $this->qualificationRepository->save($granted);

            $qualificationRecord = new ServiceRecord($soldier, ServiceRecordType::QUALIFICATION, $qualification->getName());
            $qualificationRecord->setDate($class->getStartsAt());
            $qualificationRecord->setSource(ServiceRecord::SOURCE_QUALIFICATION, $granted->getId());
            $this->serviceRecordRepository->save($qualificationRecord);
        }
    }

    private function notify(CourseClass $class, CourseClassStudent $student): void
    {
        $result = $student->getResult();
        $this->notificationService->sendNotification(new Notification(
            GenericNotificationType::TYPE,
            $student->getSoldier()->getUser(),
            [
                'title' => $class->getCourse()->getName() . ': ' . ($result?->label() ?? ''),
                'description' => 'Your result for ' . $class->getCourse()->getName() . ' is ' . strtolower($result?->label() ?? '') . '.',
                'url' => $this->urlGenerator->generate('command_net_course_class', ['id' => $class->getId()]),
            ],
        ));
    }
}
