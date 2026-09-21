<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Admin\Controller;

use Forumify\Admin\Crud\AbstractCrudController;
use MajesticDev\CommandNet\Admin\Form\RosterType;
use MajesticDev\CommandNet\Entity\Roster;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @extends AbstractCrudController<Roster>
 */
#[Route('/command-net/rosters', 'command_net_rosters')]
class RosterDefinitionController extends AbstractCrudController
{
    protected ?string $permissionView = 'command-net.admin.rosters.view';
    protected ?string $permissionCreate = 'command-net.admin.rosters.manage';
    protected ?string $permissionEdit = 'command-net.admin.rosters.manage';
    protected ?string $permissionDelete = 'command-net.admin.rosters.manage';

    protected function getEntityClass(): string
    {
        return Roster::class;
    }

    protected function getTableName(): string
    {
        return 'RosterDefinitionTable';
    }

    protected function getForm(?object $data): FormInterface
    {
        return $this->createForm(RosterType::class, $data);
    }
}
