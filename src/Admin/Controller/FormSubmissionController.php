<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Admin\Controller;

use Forumify\Admin\Crud\AbstractCrudController;
use Forumify\Core\Entity\User;
use MajesticDev\CommandNet\Admin\Form\FormSubmissionReviewType;
use MajesticDev\CommandNet\Entity\FormSubmission;
use MajesticDev\CommandNet\Service\FormSubmissionService;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The edit screen is the review screen; submissions only come from the public forms page.
 *
 * @extends AbstractCrudController<FormSubmission>
 */
#[Route('/command-net/form-submissions', 'command_net_form_submissions')]
class FormSubmissionController extends AbstractCrudController
{
    protected bool $allowCreate = false;

    protected ?string $permissionView = 'command-net.admin.forms.view';
    protected ?string $permissionEdit = 'command-net.admin.forms.manage';
    protected ?string $permissionDelete = 'command-net.admin.forms.manage';

    public function __construct(private readonly FormSubmissionService $submissionService)
    {
    }

    protected function getEntityClass(): string
    {
        return FormSubmission::class;
    }

    protected function getTableName(): string
    {
        return 'FormSubmissionTable';
    }

    protected function getForm(?object $data): FormInterface
    {
        return $this->createForm(FormSubmissionReviewType::class, $data, [
            'pending' => $data instanceof FormSubmission && $data->isPending(),
        ]);
    }

    protected function save(bool $isNew, FormInterface $form): object
    {
        /** @var FormSubmission $submission */
        $submission = $form->getData();
        if (!$submission->isPending()) {
            return $submission;
        }

        $note = trim((string)$form->get('note')->getData()) ?: null;
        $reviewer = $this->getUser();
        $reviewer = $reviewer instanceof User ? $reviewer : null;

        $this->submissionService->decide(
            $submission,
            $form->get('decision')->getData() === FormSubmissionReviewType::ACCEPT,
            $reviewer,
            $note,
        );

        return $submission;
    }
}
