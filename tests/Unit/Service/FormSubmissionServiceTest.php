<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Tests\Unit\Service;

use DateTime;
use DomainException;
use Forumify\Core\Entity\User;
use Forumify\Core\Notification\NotificationService;
use MajesticDev\CommandNet\Entity\Enum\ApplicationStatus;
use MajesticDev\CommandNet\Entity\FormDefinition;
use MajesticDev\CommandNet\Entity\FormSubmission;
use MajesticDev\CommandNet\Repository\FormSubmissionRepository;
use MajesticDev\CommandNet\Service\FormSchema;
use MajesticDev\CommandNet\Service\FormSubmissionService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class FormSubmissionServiceTest extends TestCase
{
    private FormSubmissionService $service;
    private FormSubmissionRepository&MockObject $repository;
    private NotificationService&MockObject $notificationService;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(FormSubmissionRepository::class);
        $this->notificationService = $this->createMock(NotificationService::class);
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/forms');

        $this->service = new FormSubmissionService(new FormSchema(), $this->repository, $this->notificationService, $urlGenerator);
    }

    public function testSubmitSnapshotsQuestionsAndAnswersAsText(): void
    {
        $form = $this->form("text | Full name | required\nboolean | I agree\ndate | Start date\nnumber | Age\nselect | Branch | | Army, Navy");

        $this->repository->expects($this->once())->method('save')->with($this->isInstanceOf(FormSubmission::class));

        $submission = $this->service->submit($form, $this->user(), [
            'f0_full_name' => 'Alice',
            'f1_i_agree' => true,
            'f2_start_date' => new DateTime('2026-06-01'),
            'f3_age' => 30.5,
            'f4_branch' => null,
        ]);

        $this->assertSame([
            ['label' => 'Full name', 'value' => 'Alice'],
            ['label' => 'I agree', 'value' => 'Yes'],
            ['label' => 'Start date', 'value' => '2026-06-01'],
            ['label' => 'Age', 'value' => '30.5'],
            ['label' => 'Branch', 'value' => ''],
        ], $submission->getAnswers());
        $this->assertSame('Leave request', $submission->getFormName());
        $this->assertTrue($submission->isPending());
    }

    public function testAnUncheckedBoxIsRecordedAsNo(): void
    {
        $submission = $this->service->submit($this->form('boolean | I agree'), $this->user(), ['f0_i_agree' => false]);

        $this->assertSame('No', $submission->getAnswers()[0]['value']);
    }

    public function testSubmittingToAClosedFormIsRefused(): void
    {
        $form = $this->form('text | Name');
        $form->setEnabled(false);

        $this->repository->expects($this->never())->method('save');
        $this->expectException(DomainException::class);

        $this->service->submit($form, $this->user(), []);
    }

    public function testAcceptingRecordsTheDecisionAndNotifiesTheSubmitter(): void
    {
        $submission = $this->service->submit($this->form('text | Name'), $this->user(), ['f0_name' => 'A']);
        $this->notificationService->expects($this->once())->method('sendNotification');

        $this->service->decide($submission, true, null, 'Approved.');

        $this->assertSame(ApplicationStatus::ACCEPTED, $submission->getStatus());
        $this->assertSame('Approved.', $submission->getDecisionNote());
        $this->assertNotNull($submission->getReviewedAt());
    }

    public function testDecliningRecordsTheDecision(): void
    {
        $submission = $this->service->submit($this->form('text | Name'), $this->user(), ['f0_name' => 'A']);

        $this->service->decide($submission, false, null, null);

        $this->assertSame(ApplicationStatus::DECLINED, $submission->getStatus());
    }

    public function testASubmissionCanOnlyBeReviewedOnce(): void
    {
        $submission = $this->service->submit($this->form('text | Name'), $this->user(), ['f0_name' => 'A']);
        $this->service->decide($submission, true, null, null);

        $this->expectException(DomainException::class);
        $this->service->decide($submission, false, null, null);
    }

    public function testTheSubmissionShowsItsAnswersAsPlainText(): void
    {
        $submission = $this->service->submit($this->form("text | Name\ntext | Reason"), $this->user(), [
            'f0_name' => 'Alice',
            'f1_reason' => 'Holiday',
        ]);

        $this->assertSame("Name: Alice\nReason: Holiday", $submission->getAnswersAsText());
    }

    private function form(string $fieldList): FormDefinition
    {
        $form = new FormDefinition();
        $form->setName('Leave request');
        $form->setFieldList($fieldList);

        return $form;
    }

    private function user(): User
    {
        $user = new User();
        $user->setUsername('alice');

        return $user;
    }
}
