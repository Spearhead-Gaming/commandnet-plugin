<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Admin\Controller;

use Forumify\Admin\Crud\AbstractCrudController;
use MajesticDev\CommandNet\Admin\Form\EquipmentFormType;
use MajesticDev\CommandNet\Entity\Equipment;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @extends AbstractCrudController<Equipment>
 */
#[Route('/command-net/equipment', 'command_net_equipment')]
class EquipmentController extends AbstractCrudController
{
    protected ?string $permissionView = 'command-net.admin.equipment.view';
    protected ?string $permissionCreate = 'command-net.admin.equipment.manage';
    protected ?string $permissionEdit = 'command-net.admin.equipment.manage';
    protected ?string $permissionDelete = 'command-net.admin.equipment.manage';

    protected function getEntityClass(): string
    {
        return Equipment::class;
    }

    protected function getTableName(): string
    {
        return 'EquipmentTable';
    }

    protected function getForm(?object $data): FormInterface
    {
        return $this->createForm(EquipmentFormType::class, $data);
    }
}
