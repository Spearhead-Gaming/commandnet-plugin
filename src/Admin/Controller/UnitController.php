<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Admin\Controller;

use Forumify\Admin\Crud\AbstractCrudController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Routing\Attribute\Route;
use MajesticDev\CommandNet\Admin\Form\UnitType;
use MajesticDev\CommandNet\Entity\Unit;

/**
 * @extends AbstractCrudController<Unit>
 */
#[Route('/command-net/units', 'command_net_units')]
class UnitController extends AbstractCrudController
{
    // Adds "Import ORBAT" / "Download template" next to the generic create button - see
    // templates/admin/unit_list.html.twig.
    protected string $listTemplate = '@CommandNetPlugin/admin/unit_list.html.twig';

    protected ?string $permissionView = 'command-net.admin.units.view';
    protected ?string $permissionCreate = 'command-net.admin.units.manage';
    protected ?string $permissionEdit = 'command-net.admin.units.manage';
    protected ?string $permissionDelete = 'command-net.admin.units.manage';

    protected function getEntityClass(): string
    {
        return Unit::class;
    }

    protected function getTableName(): string
    {
        return 'UnitTable';
    }

    protected function getForm(?object $data): FormInterface
    {
        return $this->createForm(UnitType::class, $data);
    }
}
