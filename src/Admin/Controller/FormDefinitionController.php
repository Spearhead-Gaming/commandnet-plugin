<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Admin\Controller;

use Forumify\Admin\Crud\AbstractCrudController;
use MajesticDev\CommandNet\Admin\Form\FormDefinitionType;
use MajesticDev\CommandNet\Entity\FormDefinition;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @extends AbstractCrudController<FormDefinition>
 */
#[Route('/command-net/forms', 'command_net_forms')]
class FormDefinitionController extends AbstractCrudController
{
    protected ?string $permissionView = 'command-net.admin.forms.view';
    protected ?string $permissionCreate = 'command-net.admin.forms.manage';
    protected ?string $permissionEdit = 'command-net.admin.forms.manage';
    protected ?string $permissionDelete = 'command-net.admin.forms.manage';

    protected function getEntityClass(): string
    {
        return FormDefinition::class;
    }

    protected function getTableName(): string
    {
        return 'FormDefinitionTable';
    }

    protected function getForm(?object $data): FormInterface
    {
        return $this->createForm(FormDefinitionType::class, $data);
    }
}
