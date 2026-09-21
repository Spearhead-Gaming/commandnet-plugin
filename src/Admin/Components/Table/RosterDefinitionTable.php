<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Admin\Components\Table;

use Forumify\Core\Component\Table\AbstractDoctrineTable;
use MajesticDev\CommandNet\Entity\Roster;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;

#[AsLiveComponent('RosterDefinitionTable', '@Forumify/components/table/table.html.twig')]
#[IsGranted('command-net.admin.rosters.view')]
class RosterDefinitionTable extends AbstractDoctrineTable
{
    protected ?string $permissionReorder = 'command-net.admin.rosters.manage';

    protected function getEntityClass(): string
    {
        return Roster::class;
    }

    protected function buildTable(): void
    {
        $this
            ->addPositionColumn()
            ->addColumn('name', [
                'field' => 'name',
            ])
            ->addActionColumn($this->renderActionColumn(...));
    }

    protected function renderActionColumn(int $id): string
    {
        if (!$this->security->isGranted('command-net.admin.rosters.manage')) {
            return '';
        }

        $actions = '';
        $actions .= $this->renderAction('forumify_admin_command_net_rosters_edit', ['identifier' => $id], 'pencil-simple-line');
        $actions .= $this->renderAction('forumify_admin_command_net_rosters_delete', ['identifier' => $id], 'x');
        return $actions;
    }
}
