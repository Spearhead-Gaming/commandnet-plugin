<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Admin\Components\Table;

use DateTime;
use Forumify\Core\Component\Table\AbstractDoctrineTable;
use Forumify\Core\Entity\User;
use MajesticDev\CommandNet\Entity\Enum\ApplicationStatus;
use MajesticDev\CommandNet\Entity\FormSubmission;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;

#[AsLiveComponent('FormSubmissionTable', '@Forumify/components/table/table.html.twig')]
#[IsGranted('command-net.admin.forms.view')]
class FormSubmissionTable extends AbstractDoctrineTable
{
    protected function getEntityClass(): string
    {
        return FormSubmission::class;
    }

    protected function buildTable(): void
    {
        $this
            ->addColumn('user', [
                'label' => 'Submitted by',
                'field' => 'user',
                'renderer' => fn (User $user) => $user->getDisplayName(),
            ])
            ->addColumn('formName', [
                'label' => 'Form',
                'field' => 'formName',
            ])
            ->addColumn('status', [
                'field' => 'status',
                'searchable' => false,
                'renderer' => fn (ApplicationStatus $status) => $status->label(),
            ])
            ->addColumn('createdAt', [
                'label' => 'Submitted',
                'field' => 'createdAt',
                'renderer' => fn (?DateTime $date) => $date?->format('Y-m-d') ?? '',
            ])
            ->addActionColumn($this->renderActionColumn(...));
    }

    protected function renderActionColumn(int $id): string
    {
        if (!$this->security->isGranted('command-net.admin.forms.manage')) {
            return '';
        }

        $actions = '';
        $actions .= $this->renderAction('forumify_admin_command_net_form_submissions_edit', ['identifier' => $id], 'pencil-simple-line');
        $actions .= $this->renderAction('forumify_admin_command_net_form_submissions_delete', ['identifier' => $id], 'x');
        return $actions;
    }
}
