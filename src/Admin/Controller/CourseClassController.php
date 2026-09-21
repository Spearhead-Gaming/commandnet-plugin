<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Admin\Controller;

use Forumify\Admin\Crud\AbstractCrudController;
use MajesticDev\CommandNet\Admin\Form\CourseClassType;
use MajesticDev\CommandNet\Entity\CourseClass;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Results are recorded from the class page on the site, not here.
 *
 * @extends AbstractCrudController<CourseClass>
 */
#[Route('/command-net/course-classes', 'command_net_course_classes')]
class CourseClassController extends AbstractCrudController
{
    protected ?string $permissionView = 'command-net.admin.courses.view';
    protected ?string $permissionCreate = 'command-net.admin.courses.manage';
    protected ?string $permissionEdit = 'command-net.admin.courses.manage';
    protected ?string $permissionDelete = 'command-net.admin.courses.manage';

    protected function getEntityClass(): string
    {
        return CourseClass::class;
    }

    protected function getTableName(): string
    {
        return 'CourseClassTable';
    }

    protected function getForm(?object $data): FormInterface
    {
        return $this->createForm(CourseClassType::class, $data);
    }
}
