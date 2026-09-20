<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Admin\Controller;

use Forumify\Admin\Crud\AbstractCrudController;
use MajesticDev\CommandNet\Admin\Form\CourseType;
use MajesticDev\CommandNet\Entity\Course;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @extends AbstractCrudController<Course>
 */
#[Route('/command-net/courses', 'command_net_courses')]
class CourseController extends AbstractCrudController
{
    protected ?string $permissionView = 'command-net.admin.courses.view';
    protected ?string $permissionCreate = 'command-net.admin.courses.manage';
    protected ?string $permissionEdit = 'command-net.admin.courses.manage';
    protected ?string $permissionDelete = 'command-net.admin.courses.manage';

    protected function getEntityClass(): string
    {
        return Course::class;
    }

    protected function getTableName(): string
    {
        return 'CourseTable';
    }

    protected function getForm(?object $data): FormInterface
    {
        return $this->createForm(CourseType::class, $data, ['current' => $data]);
    }
}
