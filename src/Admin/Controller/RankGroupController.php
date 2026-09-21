<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Admin\Controller;

use Forumify\Admin\Crud\AbstractCrudController;
use MajesticDev\CommandNet\Admin\Form\RankGroupType;
use MajesticDev\CommandNet\Entity\RankGroup;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Groups sit under the ranks permission - they only exist to organise the rank ladder.
 *
 * @extends AbstractCrudController<RankGroup>
 */
#[Route('/command-net/rank-groups', 'command_net_rank_groups')]
class RankGroupController extends AbstractCrudController
{
    protected ?string $permissionView = 'command-net.admin.ranks.view';
    protected ?string $permissionCreate = 'command-net.admin.ranks.manage';
    protected ?string $permissionEdit = 'command-net.admin.ranks.manage';
    protected ?string $permissionDelete = 'command-net.admin.ranks.manage';

    protected function getEntityClass(): string
    {
        return RankGroup::class;
    }

    protected function getTableName(): string
    {
        return 'RankGroupTable';
    }

    protected function getForm(?object $data): FormInterface
    {
        return $this->createForm(RankGroupType::class, $data);
    }
}
