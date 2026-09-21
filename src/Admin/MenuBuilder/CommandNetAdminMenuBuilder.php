<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Admin\MenuBuilder;

use Forumify\Admin\MenuBuilder\AdminMenuBuilderInterface;
use Forumify\Core\MenuBuilder\Menu;
use Forumify\Core\MenuBuilder\MenuItem;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class CommandNetAdminMenuBuilder implements AdminMenuBuilderInterface
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly Security $security,
    ) {
    }

    public function build(Menu $menu): void
    {
        $item = fn (string $label, string $route, string $permission): MenuItem => new MenuItem(
            $label,
            $this->urlGenerator->generate($route),
            ['permission' => $permission],
        );
        // The menu manager strips links the user can't see but keeps the now-empty group, so
        // groups with no visible link are left out here.
        $group = fn (string $label, array $items): ?Menu => array_filter(
            $items,
            fn (MenuItem $i): bool => $this->security->isGranted((string) $i->getPermission()),
        ) === [] ? null : new Menu($label, [], $items);

        $groups = array_filter([
            $group('Personnel', [
                $item('Personnel', 'forumify_admin_command_net_personnel_list', 'command-net.admin.personnel.view'),
                $item('Enlistment', 'forumify_admin_command_net_enlistment_list', 'command-net.admin.enlistment.view'),
                $item('Ranks', 'forumify_admin_command_net_ranks_list', 'command-net.admin.ranks.view'),
                $item('Rank Groups', 'forumify_admin_command_net_rank_groups_list', 'command-net.admin.ranks.view'),
                $item('Specialties', 'forumify_admin_command_net_specialties_list', 'command-net.admin.specialties.view'),
            ]),
            $group('Units & Rosters', [
                $item('Units', 'forumify_admin_command_net_units_list', 'command-net.admin.units.view'),
                $item('Positions', 'forumify_admin_command_net_positions_list', 'command-net.admin.units.view'),
                $item('Rosters', 'forumify_admin_command_net_rosters_list', 'command-net.admin.rosters.view'),
                $item('Equipment', 'forumify_admin_command_net_equipment_list', 'command-net.admin.equipment.view'),
                $item('ORBAT Export', 'forumify_admin_command_net_orbat', 'command-net.admin.units.view'),
            ]),
            $group('Training & Awards', [
                $item('Courses', 'forumify_admin_command_net_courses_list', 'command-net.admin.courses.view'),
                $item('Course Classes', 'forumify_admin_command_net_course_classes_list', 'command-net.admin.courses.view'),
                $item('Qualifications', 'forumify_admin_command_net_qualifications_list', 'command-net.admin.qualifications.view'),
                $item('Awards', 'forumify_admin_command_net_awards_list', 'command-net.admin.awards.view'),
            ]),
            $group('Operations & Forms', [
                $item('Operations', 'forumify_admin_command_net_operations_list', 'command-net.admin.operations.view'),
                $item('Forms', 'forumify_admin_command_net_forms_list', 'command-net.admin.forms.view'),
                $item('Form Submissions', 'forumify_admin_command_net_form_submissions_list', 'command-net.admin.forms.view'),
                $item('Documents', 'forumify_admin_command_net_documents_list', 'command-net.admin.documents.view'),
            ]),
            $group('Settings', [
                $item('Enlistment Settings', 'forumify_admin_command_net_enlistment_settings', 'command-net.admin.enlistment.manage'),
                $item('AWOL Settings', 'forumify_admin_command_net_awol_settings', 'command-net.admin.awol.manage'),
                $item('Report In Settings', 'forumify_admin_command_net_report_in_settings', 'command-net.admin.reportin.manage'),
                $item('Squad XML', 'forumify_admin_command_net_squad_xml_settings', 'command-net.admin.squadxml.manage'),
            ]),
        ]);

        if ($groups !== []) {
            $menu->addItem(new Menu('Command Net', ['icon' => 'ph ph-shield-star'], array_values($groups)));
        }
    }
}
