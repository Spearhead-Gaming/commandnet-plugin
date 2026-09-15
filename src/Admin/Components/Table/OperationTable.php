<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Admin\Components\Table;

use Forumify\Core\Component\Table\AbstractDoctrineTable;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use MajesticDev\CommandNet\Entity\Enum\OperationStatus;
use MajesticDev\CommandNet\Entity\Enum\OperationType;
use MajesticDev\CommandNet\Entity\Operation;

#[AsLiveComponent('OperationTable', '@Forumify/components/table/table.html.twig')]
#[IsGranted('command-net.admin.operations.view')]
class OperationTable extends AbstractDoctrineTable
{
    protected function getEntityClass(): string
    {
        return Operation::class;
    }

    protected function buildTable(): void
    {
        $this
            ->addColumn('title', [
                'field' => 'title',
            ])
            ->addColumn('type', [
                'field' => 'type',
                'searchable' => false,
                'renderer' => fn (OperationType $type) => $type->label(),
            ])
            ->addColumn('startDateTime', [
                'label' => 'Starts',
                'field' => 'startDateTime',
                'searchable' => false,
            ])
            ->addColumn('status', [
                'field' => 'status',
                'searchable' => false,
                'renderer' => fn (OperationStatus $status) => $status->label(),
            ])
            ->addActionColumn($this->renderActionColumn(...));
    }

    protected function renderActionColumn(int $id): string
    {
        $actions = '';
        $actions .= $this->renderAction('command_net_operation_detail', ['id' => $id], 'eye');

        if ($this->security->isGranted('command-net.admin.operations.manage')) {
            $actions .= $this->renderAction('forumify_admin_command_net_operations_edit', ['identifier' => $id], 'pencil-simple-line');
            $actions .= $this->renderAction('forumify_admin_command_net_operations_delete', ['identifier' => $id], 'x');
        }

        return $actions;
    }
}
