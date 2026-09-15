<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Admin\Components\Table;

use Forumify\Core\Component\Table\AbstractDoctrineTable;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use MajesticDev\CommandNet\Entity\Award;

#[AsLiveComponent('AwardTable', '@Forumify/components/table/table.html.twig')]
#[IsGranted('command-net.admin.awards.view')]
class AwardTable extends AbstractDoctrineTable
{
    protected ?string $permissionReorder = 'command-net.admin.awards.manage';

    protected function getEntityClass(): string
    {
        return Award::class;
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
        if (!$this->security->isGranted('command-net.admin.awards.manage')) {
            return '';
        }

        $actions = '';
        $actions .= $this->renderAction('forumify_admin_command_net_awards_edit', ['identifier' => $id], 'pencil-simple-line');
        $actions .= $this->renderAction('forumify_admin_command_net_awards_delete', ['identifier' => $id], 'x');
        return $actions;
    }
}
