<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Admin\Components\Table;

use Forumify\Core\Component\Table\AbstractDoctrineTable;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Entity\Unit;

/**
 * Note: position is currently a single global ordering, not scoped per-parent. Good
 * enough for most unit counts; if your tree gets deep, swap addPositionColumn() for a
 * queryMutator that scopes reordering to siblings sharing the same parent.
 */
#[AsLiveComponent('UnitTable', '@Forumify/components/table/table.html.twig')]
#[IsGranted('command-net.admin.units.view')]
class UnitTable extends AbstractDoctrineTable
{
    protected ?string $permissionReorder = 'command-net.admin.units.manage';

    protected function getEntityClass(): string
    {
        return Unit::class;
    }

    protected function buildTable(): void
    {
        $this
            ->addPositionColumn()
            ->addColumn('name', [
                'field' => 'name',
            ])
            ->addColumn('abbreviation', [
                'field' => 'abbreviation',
            ])
            ->addColumn('parent', [
                'label' => 'Parent Unit',
                'field' => 'parent',
                'searchable' => false,
                'sortable' => false,
                'renderer' => fn (?Unit $parent) => $parent !== null ? (string)$parent : '—',
            ])
            ->addColumn('commander', [
                'field' => 'commander',
                'searchable' => false,
                'sortable' => false,
                'renderer' => fn (?SoldierProfile $commander) => $commander !== null ? (string)$commander : 'Vacant',
            ])
            ->addActionColumn($this->renderActionColumn(...));
    }

    protected function renderActionColumn(int $id): string
    {
        if (!$this->security->isGranted('command-net.admin.units.manage')) {
            return '';
        }

        $actions = '';
        $actions .= $this->renderAction('forumify_admin_command_net_units_edit', ['identifier' => $id], 'pencil-simple-line');
        $actions .= $this->renderAction('forumify_admin_command_net_units_delete', ['identifier' => $id], 'x');
        return $actions;
    }
}
