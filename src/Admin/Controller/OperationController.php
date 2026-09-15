<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Admin\Controller;

use Forumify\Admin\Crud\AbstractCrudController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Routing\Attribute\Route;
use MajesticDev\CommandNet\Admin\Form\OperationFormType;
use MajesticDev\CommandNet\Entity\Operation;

/**
 * @extends AbstractCrudController<Operation>
 */
#[Route('/command-net/operations', 'command_net_operations')]
class OperationController extends AbstractCrudController
{
    protected ?string $permissionView = 'command-net.admin.operations.view';
    protected ?string $permissionCreate = 'command-net.admin.operations.manage';
    protected ?string $permissionEdit = 'command-net.admin.operations.manage';
    protected ?string $permissionDelete = 'command-net.admin.operations.manage';

    protected function getEntityClass(): string
    {
        return Operation::class;
    }

    protected function getTableName(): string
    {
        return 'OperationTable';
    }

    protected function getForm(?object $data): FormInterface
    {
        return $this->createForm(OperationFormType::class, $data);
    }
}
