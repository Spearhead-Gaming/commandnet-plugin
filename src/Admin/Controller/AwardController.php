<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Admin\Controller;

use Forumify\Admin\Crud\AbstractCrudController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Routing\Attribute\Route;
use MajesticDev\CommandNet\Admin\Form\AwardType;
use MajesticDev\CommandNet\Entity\Award;

/**
 * @extends AbstractCrudController<Award>
 */
#[Route('/command-net/awards', 'command_net_awards')]
class AwardController extends AbstractCrudController
{
    protected ?string $permissionView = 'command-net.admin.awards.view';
    protected ?string $permissionCreate = 'command-net.admin.awards.manage';
    protected ?string $permissionEdit = 'command-net.admin.awards.manage';
    protected ?string $permissionDelete = 'command-net.admin.awards.manage';

    protected function getEntityClass(): string
    {
        return Award::class;
    }

    protected function getTableName(): string
    {
        return 'AwardTable';
    }

    protected function getForm(?object $data): FormInterface
    {
        return $this->createForm(AwardType::class, $data);
    }
}
