<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Admin\Controller;

use Forumify\Admin\Crud\AbstractCrudController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Routing\Attribute\Route;
use MajesticDev\CommandNet\Admin\Form\SoldierProfileType;
use MajesticDev\CommandNet\Entity\SoldierProfile;

/**
 * @extends AbstractCrudController<SoldierProfile>
 */
#[Route('/command-net/personnel', 'command_net_personnel')]
class SoldierProfileController extends AbstractCrudController
{
    // Adds an optional "Create/View ID Card" action when majesticdev/forumify-id-card-plugin
    // is installed; a no-op include on any install that doesn't have it.
    protected string $formTemplate = '@CommandNetPlugin/admin/soldier_profile/form.html.twig';

    protected ?string $permissionView = 'command-net.admin.personnel.view';
    protected ?string $permissionCreate = 'command-net.admin.personnel.manage';
    protected ?string $permissionEdit = 'command-net.admin.personnel.manage';
    protected ?string $permissionDelete = 'command-net.admin.personnel.manage';

    protected function getEntityClass(): string
    {
        return SoldierProfile::class;
    }

    protected function getTableName(): string
    {
        return 'SoldierProfileTable';
    }

    protected function getForm(?object $data): FormInterface
    {
        return $this->createForm(SoldierProfileType::class, $data);
    }
}
