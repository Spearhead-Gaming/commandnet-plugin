<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Admin\Components\Table;

use DateTime;
use Forumify\Core\Component\Table\AbstractDoctrineTable;
use MajesticDev\CommandNet\Entity\Course;
use MajesticDev\CommandNet\Entity\CourseClass;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;

#[AsLiveComponent('CourseClassTable', '@Forumify/components/table/table.html.twig')]
#[IsGranted('command-net.admin.courses.view')]
class CourseClassTable extends AbstractDoctrineTable
{
    protected function getEntityClass(): string
    {
        return CourseClass::class;
    }

    protected function buildTable(): void
    {
        $this
            ->addColumn('course', [
                'field' => 'course',
                'searchable' => false,
                'renderer' => fn (Course $course) => $course->getName(),
            ])
            ->addColumn('startsAt', [
                'label' => 'Starts',
                'field' => 'startsAt',
                'renderer' => fn (DateTime $date) => $date->format('Y-m-d H:i'),
            ])
            ->addColumn('processed', [
                'label' => 'Results in',
                'field' => 'processed',
                'searchable' => false,
                'renderer' => fn (bool $processed) => $processed ? 'Yes' : 'No',
            ])
            ->addActionColumn($this->renderActionColumn(...));
    }

    protected function renderActionColumn(int $id): string
    {
        if (!$this->security->isGranted('command-net.admin.courses.manage')) {
            return '';
        }

        $actions = '';
        $actions .= $this->renderAction('forumify_admin_command_net_course_classes_edit', ['identifier' => $id], 'pencil-simple-line');
        $actions .= $this->renderAction('forumify_admin_command_net_course_classes_delete', ['identifier' => $id], 'x');
        return $actions;
    }
}
