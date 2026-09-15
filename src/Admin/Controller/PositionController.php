<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Admin\Controller;

use Forumify\Admin\Crud\AbstractCrudController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Routing\Attribute\Route;
use MajesticDev\CommandNet\Admin\Form\PositionType;
use MajesticDev\CommandNet\Entity\Position;

/**
 * Positions live under the same "units" permission — they're a supporting catalog for
 * assignments rather than a standalone concern worth its own permission branch.
 *
 * @extends AbstractCrudController<Position>
 */
#[Route('/command-net/positions', 'command_net_positions')]
class PositionController extends AbstractCrudController
{
    protected ?string $permissionView = 'command-net.admin.units.view';
    protected ?string $permissionCreate = 'command-net.admin.units.manage';
    protected ?string $permissionEdit = 'command-net.admin.units.manage';
    protected ?string $permissionDelete = 'command-net.admin.units.manage';

    protected function getEntityClass(): string
    {
        return Position::class;
    }

    protected function getTableName(): string
    {
        return 'PositionTable';
    }

    protected function getForm(?object $data): FormInterface
    {
        return $this->createForm(PositionType::class, $data);
    }
}
