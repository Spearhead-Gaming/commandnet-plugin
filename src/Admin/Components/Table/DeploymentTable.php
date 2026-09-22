<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Admin\Components\Table;

use DateTimeInterface;
use Forumify\Core\Component\Table\AbstractDoctrineTable;
use MajesticDev\CommandNet\Entity\Deployment;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;

#[AsLiveComponent('DeploymentTable', '@Forumify/components/table/table.html.twig')]
#[IsGranted('command-net.admin.deployments.view')]
class DeploymentTable extends AbstractDoctrineTable
{
    protected function getEntityClass(): string
    {
        return Deployment::class;
    }

    protected function buildTable(): void
    {
        $this
            ->addColumn('name', [
                'field' => 'name',
            ])
            ->addColumn('startDate', [
                'label' => 'Starts',
                'field' => 'startDate',
                'searchable' => false,
                'renderer' => fn (DateTimeInterface $date) => $date->format('Y-m-d'),
            ])
            ->addColumn('endDate', [
                'label' => 'Ends',
                'field' => 'endDate',
                'searchable' => false,
                'renderer' => fn (DateTimeInterface $date) => $date->format('Y-m-d'),
            ])
            ->addActionColumn($this->renderActionColumn(...));
    }

    protected function renderActionColumn(int $id): string
    {
        if (!$this->security->isGranted('command-net.admin.deployments.manage')) {
            return '';
        }

        $actions = '';
        $actions .= $this->renderAction('forumify_admin_command_net_deployment_generate', ['id' => $id], 'calendar-plus');
        $actions .= $this->renderAction('forumify_admin_command_net_deployments_edit', ['identifier' => $id], 'pencil-simple-line');
        $actions .= $this->renderAction('forumify_admin_command_net_deployments_delete', ['identifier' => $id], 'x');
        return $actions;
    }
}
