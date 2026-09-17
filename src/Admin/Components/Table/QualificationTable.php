<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Admin\Components\Table;

use Forumify\Core\Component\Table\AbstractDoctrineTable;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use MajesticDev\CommandNet\Entity\Enum\QualificationTier;
use MajesticDev\CommandNet\Entity\Qualification;

#[AsLiveComponent('QualificationTable', '@Forumify/components/table/table.html.twig')]
#[IsGranted('command-net.admin.qualifications.view')]
class QualificationTable extends AbstractDoctrineTable
{
    protected ?string $permissionReorder = 'command-net.admin.qualifications.manage';

    protected function getEntityClass(): string
    {
        return Qualification::class;
    }

    protected function buildTable(): void
    {
        $this
            ->addPositionColumn()
            ->addColumn('name', [
                'field' => 'name',
            ])
            ->addColumn('tier', [
                'field' => 'tier',
                'searchable' => false,
                'renderer' => fn (?QualificationTier $tier) => $tier?->label() ?? '-',
            ])
            ->addActionColumn($this->renderActionColumn(...));
    }

    protected function renderActionColumn(int $id): string
    {
        if (!$this->security->isGranted('command-net.admin.qualifications.manage')) {
            return '';
        }

        $actions = '';
        $actions .= $this->renderAction('forumify_admin_command_net_qualifications_edit', ['identifier' => $id], 'pencil-simple-line');
        $actions .= $this->renderAction('forumify_admin_command_net_qualifications_delete', ['identifier' => $id], 'x');
        return $actions;
    }
}
