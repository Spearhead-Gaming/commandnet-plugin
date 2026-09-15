<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Admin\Controller;

use Forumify\Admin\Crud\AbstractCrudController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Routing\Attribute\Route;
use MajesticDev\CommandNet\Admin\Form\QualificationType;
use MajesticDev\CommandNet\Entity\Qualification;

/**
 * @extends AbstractCrudController<Qualification>
 */
#[Route('/command-net/qualifications', 'command_net_qualifications')]
class QualificationController extends AbstractCrudController
{
    protected ?string $permissionView = 'command-net.admin.qualifications.view';
    protected ?string $permissionCreate = 'command-net.admin.qualifications.manage';
    protected ?string $permissionEdit = 'command-net.admin.qualifications.manage';
    protected ?string $permissionDelete = 'command-net.admin.qualifications.manage';

    protected function getEntityClass(): string
    {
        return Qualification::class;
    }

    protected function getTableName(): string
    {
        return 'QualificationTable';
    }

    protected function getForm(?object $data): FormInterface
    {
        return $this->createForm(QualificationType::class, $data);
    }
}
