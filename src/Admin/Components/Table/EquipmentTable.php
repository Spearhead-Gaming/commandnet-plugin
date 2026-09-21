<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Admin\Components\Table;

use Forumify\Core\Component\Table\AbstractDoctrineTable;
use MajesticDev\CommandNet\Entity\Enum\EquipmentType;
use MajesticDev\CommandNet\Entity\Equipment;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;

#[AsLiveComponent('EquipmentTable', '@Forumify/components/table/table.html.twig')]
#[IsGranted('command-net.admin.equipment.view')]
class EquipmentTable extends AbstractDoctrineTable
{
    protected function getEntityClass(): string
    {
        return Equipment::class;
    }

    protected function buildTable(): void
    {
        $this
            ->addColumn('name', [
                'field' => 'name',
            ])
            ->addColumn('type', [
                'field' => 'type',
                'searchable' => false,
                'renderer' => fn (EquipmentType $type) => $type->label(),
            ])
            ->addActionColumn($this->renderActionColumn(...));
    }

    protected function renderActionColumn(int $id): string
    {
        if (!$this->security->isGranted('command-net.admin.equipment.manage')) {
            return '';
        }

        $actions = '';
        $actions .= $this->renderAction('forumify_admin_command_net_equipment_edit', ['identifier' => $id], 'pencil-simple-line');
        $actions .= $this->renderAction('forumify_admin_command_net_equipment_delete', ['identifier' => $id], 'x');
        return $actions;
    }
}
