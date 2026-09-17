<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet;

use Forumify\Plugin\AbstractForumifyPlugin;
use Forumify\Plugin\PluginMetadata;

/**
 * The entry point forumify uses to recognise this package as a plugin.
 *
 * Override getPermissions() to declare permissions your plugin checks, for example:
 *
 *     public function getPermissions(): array
 *     {
 *         return ['admin' => ['example' => ['view', 'manage']]];
 *     }
 *
 * They are then checked as "<slugged-plugin-name>.admin.example.view".
 */
class CommandNetPlugin extends AbstractForumifyPlugin
{
    public function getPluginMetadata(): PluginMetadata
    {
        return new PluginMetadata(
            'Command Net',
            'Your Unit',
            'Personnel & unit management system built for our community.',
            'https://example.com',
        );
    }

    /**
     * Permissions are checked as "command-net.<area>.<action>", e.g.
     * "command-net.admin.personnel.manage" or "command-net.operations.rsvp". The prefix
     * comes from Symfony's AsciiSlugger on the plugin's display name ("Command Net"),
     * which hyphenates rather than underscores - worth remembering since routes and
     * translation keys in this plugin use underscores (command_net_*) instead.
     *
     * Every module below gets its own branch so you can hand out access module-by-module
     * (e.g. a Training NCO who can manage courses but not the roster).
     */
    public function getPermissions(): array
    {
        return [
            'admin' => [
                'personnel' => ['view', 'manage'],
                'units' => ['view', 'manage'],
                'ranks' => ['view', 'manage'],
                'awards' => ['view', 'manage'],
                'qualifications' => ['view', 'manage'],
                'operations' => ['view', 'manage'],
                'attendance' => ['view', 'manage'],
                'forms' => ['view', 'manage'],
                'courses' => ['view', 'manage'],
                'reportin' => ['view', 'manage'],
            ],
            'roster' => ['view'],
            'qualifications' => ['view'],
            'operations' => ['view', 'rsvp', 'submit_aar'],
            'attendance' => ['view_own', 'view_all'],
            'forms' => ['submit'],
            'courses' => ['enroll'],
            'reportin' => ['submit'],
        ];
    }
}
