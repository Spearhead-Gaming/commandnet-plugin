<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Admin\Controller;

use Forumify\Admin\Crud\AbstractCrudController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Routing\Attribute\Route;
use MajesticDev\CommandNet\Admin\Form\RankType;
use MajesticDev\CommandNet\Entity\Rank;

/**
 * @extends AbstractCrudController<Rank>
 */
#[Route('/command-net/ranks', 'command_net_ranks')]
class RankController extends AbstractCrudController
{
    protected ?string $permissionView = 'command-net.admin.ranks.view';
    protected ?string $permissionCreate = 'command-net.admin.ranks.manage';
    protected ?string $permissionEdit = 'command-net.admin.ranks.manage';
    protected ?string $permissionDelete = 'command-net.admin.ranks.manage';

    protected function getEntityClass(): string
    {
        return Rank::class;
    }

    protected function getTableName(): string
    {
        return 'RankTable';
    }

    protected function getForm(?object $data): FormInterface
    {
        return $this->createForm(RankType::class, $data);
    }
}
