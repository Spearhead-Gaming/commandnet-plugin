<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Admin\Components\Table;

use Forumify\Core\Component\Table\AbstractDoctrineTable;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use MajesticDev\CommandNet\Entity\Assignment;
use MajesticDev\CommandNet\Entity\Enum\SoldierStatus;
use MajesticDev\CommandNet\Entity\Rank;
use MajesticDev\CommandNet\Entity\SoldierProfile;

#[AsLiveComponent('SoldierProfileTable', '@Forumify/components/table/table.html.twig')]
#[IsGranted('command-net.admin.personnel.view')]
class SoldierProfileTable extends AbstractDoctrineTable
{
    protected function getEntityClass(): string
    {
        return SoldierProfile::class;
    }

    protected function buildTable(): void
    {
        $this
            // "user" is a real single-level association, safe to sort/search on.
            ->addColumn('user', [
                'label' => 'Name',
                'field' => 'user',
                'renderer' => fn (object $user) => $user->getDisplayName(),
            ])
            ->addColumn('rank', [
                'field' => 'rank',
                'searchable' => false,
                'renderer' => fn (?Rank $rank) => $rank !== null ? (string)$rank : 'Unranked',
            ])
            // primaryAssignment is a derived getter, not a mapped association — kept as
            // a single-segment field so the table never tries to build a DQL join on it.
            ->addColumn('unit', [
                'field' => 'primaryAssignment',
                'searchable' => false,
                'sortable' => false,
                'renderer' => fn (?Assignment $assignment) => $assignment !== null ? (string)$assignment->getUnit() : '—',
            ])
            ->addColumn('status', [
                'field' => 'status',
                'searchable' => false,
                'renderer' => fn (SoldierStatus $status) => $status->label(),
            ])
            ->addActionColumn($this->renderActionColumn(...));
    }

    protected function renderActionColumn(int $id): string
    {
        $actions = '';
        $actions .= $this->renderAction('forumify_admin_command_net_personnel_edit', ['identifier' => $id], 'pencil-simple-line');

        if ($this->security->isGranted('command-net.admin.personnel.manage')) {
            $actions .= $this->renderAction('forumify_admin_command_net_personnel_delete', ['identifier' => $id], 'x');
        }

        return $actions;
    }
}
