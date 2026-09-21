<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Admin\Components\Table;

use Forumify\Core\Component\Table\AbstractDoctrineTable;
use MajesticDev\CommandNet\Entity\Specialty;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;

#[AsLiveComponent('SpecialtyTable', '@Forumify/components/table/table.html.twig')]
#[IsGranted('command-net.admin.specialties.view')]
class SpecialtyTable extends AbstractDoctrineTable
{
    protected function getEntityClass(): string
    {
        return Specialty::class;
    }

    protected function buildTable(): void
    {
        $this
            ->addColumn('name', [
                'field' => 'name',
            ])
            ->addColumn('abbreviation', [
                'field' => 'abbreviation',
            ])
            ->addActionColumn($this->renderActionColumn(...));
    }

    protected function renderActionColumn(int $id): string
    {
        if (!$this->security->isGranted('command-net.admin.specialties.manage')) {
            return '';
        }

        $actions = '';
        $actions .= $this->renderAction('forumify_admin_command_net_specialties_edit', ['identifier' => $id], 'pencil-simple-line');
        $actions .= $this->renderAction('forumify_admin_command_net_specialties_delete', ['identifier' => $id], 'x');
        return $actions;
    }
}
