<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Admin\MenuBuilder;

use Forumify\Admin\MenuBuilder\AdminMenuBuilderInterface;
use Forumify\Core\MenuBuilder\Menu;
use Forumify\Core\MenuBuilder\MenuItem;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class CommandNetAdminMenuBuilder implements AdminMenuBuilderInterface
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function build(Menu $menu): void
    {
        $url = fn (string $route): string => $this->urlGenerator->generate($route);

        $menu->addItem(new Menu('Command Net', ['icon' => 'ph ph-shield-star'], [
            new MenuItem('Personnel', $url('forumify_admin_command_net_personnel_list'), [
                'permission' => 'command-net.admin.personnel.view',
            ]),
            new MenuItem('Units', $url('forumify_admin_command_net_units_list'), [
                'permission' => 'command-net.admin.units.view',
            ]),
            new MenuItem('Ranks', $url('forumify_admin_command_net_ranks_list'), [
                'permission' => 'command-net.admin.ranks.view',
            ]),
            new MenuItem('Rank Groups', $url('forumify_admin_command_net_rank_groups_list'), [
                'permission' => 'command-net.admin.ranks.view',
            ]),
            new MenuItem('Rosters', $url('forumify_admin_command_net_rosters_list'), [
                'permission' => 'command-net.admin.rosters.view',
            ]),
            new MenuItem('Positions', $url('forumify_admin_command_net_positions_list'), [
                'permission' => 'command-net.admin.units.view',
            ]),
            new MenuItem('Specialties', $url('forumify_admin_command_net_specialties_list'), [
                'permission' => 'command-net.admin.specialties.view',
            ]),
            new MenuItem('Equipment', $url('forumify_admin_command_net_equipment_list'), [
                'permission' => 'command-net.admin.equipment.view',
            ]),
            new MenuItem('Documents', $url('forumify_admin_command_net_documents_list'), [
                'permission' => 'command-net.admin.documents.view',
            ]),
            new MenuItem('Forms', $url('forumify_admin_command_net_forms_list'), [
                'permission' => 'command-net.admin.forms.view',
            ]),
            new MenuItem('Form Submissions', $url('forumify_admin_command_net_form_submissions_list'), [
                'permission' => 'command-net.admin.forms.view',
            ]),
            new MenuItem('Courses', $url('forumify_admin_command_net_courses_list'), [
                'permission' => 'command-net.admin.courses.view',
            ]),
            new MenuItem('Course Classes', $url('forumify_admin_command_net_course_classes_list'), [
                'permission' => 'command-net.admin.courses.view',
            ]),
            new MenuItem('Awards', $url('forumify_admin_command_net_awards_list'), [
                'permission' => 'command-net.admin.awards.view',
            ]),
            new MenuItem('Qualifications', $url('forumify_admin_command_net_qualifications_list'), [
                'permission' => 'command-net.admin.qualifications.view',
            ]),
            new MenuItem('Operations', $url('forumify_admin_command_net_operations_list'), [
                'permission' => 'command-net.admin.operations.view',
            ]),
            new MenuItem('Enlistment', $url('forumify_admin_command_net_enlistment_list'), [
                'permission' => 'command-net.admin.enlistment.view',
            ]),
            new MenuItem('Enlistment Settings', $url('forumify_admin_command_net_enlistment_settings'), [
                'permission' => 'command-net.admin.enlistment.manage',
            ]),
            new MenuItem('AWOL Settings', $url('forumify_admin_command_net_awol_settings'), [
                'permission' => 'command-net.admin.awol.manage',
            ]),
            new MenuItem('Report In Settings', $url('forumify_admin_command_net_report_in_settings'), [
                'permission' => 'command-net.admin.reportin.manage',
            ]),
        ]));
    }
}
