<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Admin\Controller;

use Forumify\Admin\Crud\AbstractCrudController;
use MajesticDev\CommandNet\Admin\Form\SpecialtyType;
use MajesticDev\CommandNet\Entity\Specialty;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @extends AbstractCrudController<Specialty>
 */
#[Route('/command-net/specialties', 'command_net_specialties')]
class SpecialtyController extends AbstractCrudController
{
    protected ?string $permissionView = 'command-net.admin.specialties.view';
    protected ?string $permissionCreate = 'command-net.admin.specialties.manage';
    protected ?string $permissionEdit = 'command-net.admin.specialties.manage';
    protected ?string $permissionDelete = 'command-net.admin.specialties.manage';

    protected function getEntityClass(): string
    {
        return Specialty::class;
    }

    protected function getTableName(): string
    {
        return 'SpecialtyTable';
    }

    protected function getForm(?object $data): FormInterface
    {
        return $this->createForm(SpecialtyType::class, $data);
    }
}
