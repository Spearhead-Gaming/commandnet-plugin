<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Admin\Components\Table;

use Forumify\Core\Component\Table\AbstractDoctrineTable;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use MajesticDev\CommandNet\Entity\Position;

#[AsLiveComponent('PositionTable', '@Forumify/components/table/table.html.twig')]
#[IsGranted('command-net.admin.units.view')]
class PositionTable extends AbstractDoctrineTable
{
    protected function getEntityClass(): string
    {
        return Position::class;
    }

    protected function buildTable(): void
    {
        $this
            ->addColumn('title', [
                'field' => 'title',
            ])
            ->addActionColumn($this->renderActionColumn(...));
    }

    protected function renderActionColumn(int $id): string
    {
        if (!$this->security->isGranted('command-net.admin.units.manage')) {
            return '';
        }

        $actions = '';
        $actions .= $this->renderAction('forumify_admin_command_net_positions_edit', ['identifier' => $id], 'pencil-simple-line');
        $actions .= $this->renderAction('forumify_admin_command_net_positions_delete', ['identifier' => $id], 'x');
        return $actions;
    }
}
