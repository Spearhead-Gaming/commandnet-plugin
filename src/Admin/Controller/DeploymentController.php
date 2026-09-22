<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Admin\Controller;

use Forumify\Admin\Crud\AbstractCrudController;
use MajesticDev\CommandNet\Admin\Form\DeploymentType;
use MajesticDev\CommandNet\Entity\Deployment;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @extends AbstractCrudController<Deployment>
 */
#[Route('/command-net/deployments', 'command_net_deployments')]
class DeploymentController extends AbstractCrudController
{
    protected ?string $permissionView = 'command-net.admin.deployments.view';
    protected ?string $permissionCreate = 'command-net.admin.deployments.manage';
    protected ?string $permissionEdit = 'command-net.admin.deployments.manage';
    protected ?string $permissionDelete = 'command-net.admin.deployments.manage';

    protected function getEntityClass(): string
    {
        return Deployment::class;
    }

    protected function getTableName(): string
    {
        return 'DeploymentTable';
    }

    protected function getForm(?object $data): FormInterface
    {
        return $this->createForm(DeploymentType::class, $data);
    }
}
