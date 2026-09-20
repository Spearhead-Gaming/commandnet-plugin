<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Admin\Components\Table;

use DateTime;
use Forumify\Core\Component\Table\AbstractDoctrineTable;
use Forumify\Core\Entity\User;
use MajesticDev\CommandNet\Entity\EnlistmentApplication;
use MajesticDev\CommandNet\Entity\Enum\ApplicationStatus;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;

#[AsLiveComponent('EnlistmentApplicationTable', '@Forumify/components/table/table.html.twig')]
#[IsGranted('command-net.admin.enlistment.view')]
class EnlistmentApplicationTable extends AbstractDoctrineTable
{
    protected function getEntityClass(): string
    {
        return EnlistmentApplication::class;
    }

    protected function buildTable(): void
    {
        $this
            ->addColumn('user', [
                'label' => 'Applicant',
                'field' => 'user',
                'renderer' => fn (User $user) => $user->getDisplayName(),
            ])
            ->addColumn('callsign', [
                'field' => 'callsign',
            ])
            ->addColumn('status', [
                'field' => 'status',
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
        if (!$this->security->isGranted('command-net.admin.enlistment.manage')) {
            return '';
        }

        $actions = '';
        $actions .= $this->renderAction('forumify_admin_command_net_enlistment_edit', ['identifier' => $id], 'pencil-simple-line');
        $actions .= $this->renderAction('forumify_admin_command_net_enlistment_delete', ['identifier' => $id], 'x');
        return $actions;
    }
}
