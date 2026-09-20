<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Admin\Components\Table;

use Forumify\Core\Component\Table\AbstractDoctrineTable;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use MajesticDev\CommandNet\Entity\Rank;
use MajesticDev\CommandNet\Entity\RankGroup;

#[AsLiveComponent('RankTable', '@Forumify/components/table/table.html.twig')]
#[IsGranted('command-net.admin.ranks.view')]
class RankTable extends AbstractDoctrineTable
{
    protected ?string $permissionReorder = 'command-net.admin.ranks.manage';

    protected function getEntityClass(): string
    {
        return Rank::class;
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
            ->addColumn('group', [
                'field' => 'group',
                'searchable' => false,
                'sortable' => false,
                'renderer' => fn (?RankGroup $group) => $group !== null ? $group->getName() : '',
            ])
            ->addColumn('payGrade', [
                'label' => 'Pay Grade',
                'field' => 'payGrade',
            ])
            ->addActionColumn($this->renderActionColumn(...));
    }

    protected function renderActionColumn(int $id): string
    {
        if (!$this->security->isGranted('command-net.admin.ranks.manage')) {
            return '';
        }

        $actions = '';
        $actions .= $this->renderAction('forumify_admin_command_net_ranks_edit', ['identifier' => $id], 'pencil-simple-line');
        $actions .= $this->renderAction('forumify_admin_command_net_ranks_delete', ['identifier' => $id], 'x');
        return $actions;
    }
}
