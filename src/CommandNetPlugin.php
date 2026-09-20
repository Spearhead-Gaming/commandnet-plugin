<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet;

use Forumify\Calendar\ForumifyCalendarPlugin;
use MajesticDev\Discord\CommandNetDiscordPlugin;
use Forumify\Plugin\AbstractForumifyPlugin;
use Forumify\Plugin\PluginMetadata;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

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
            'MDEV ',
            'Personnel & unit management system built for our community.',
            // TODO: replace with real domain once purchased
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
                'personnel' => ['view', 'manage', 'discharge'],
                'units' => ['view', 'manage'],
                'ranks' => ['view', 'manage'],
                'awards' => ['view', 'manage'],
                'qualifications' => ['view', 'manage'],
                'operations' => ['view', 'manage'],
                'attendance' => ['view', 'manage'],
                'forms' => ['view', 'manage'],
                'courses' => ['view', 'manage'],
                'reportin' => ['view', 'manage'],
                'awol' => ['manage'],
                'enlistment' => ['view', 'manage'],
                'specialties' => ['view', 'manage'],
                'equipment' => ['view', 'manage'],
            ],
            'roster' => ['view'],
            'qualifications' => ['view'],
            'operations' => ['view', 'rsvp', 'submit_aar'],
            'attendance' => ['view_own', 'view_all'],
            'promotions' => ['view'],
            'forms' => ['submit'],
            'courses' => ['enroll'],
            'reportin' => ['submit'],
        ];
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        parent::loadExtension($config, $container, $builder);

        if ($this->isPluginLoaded($builder, ForumifyCalendarPlugin::class)) {
            $container->import($this->getPath() . '/config/calendar.php');
        }

        if ($this->isPluginLoaded($builder, CommandNetDiscordPlugin::class)) {
            $container->import($this->getPath() . '/config/discord.php');
        }
    }

    /**
     * @param class-string $pluginClass
     */
    private function isPluginLoaded(ContainerBuilder $builder, string $pluginClass): bool
    {
        /** @var array<string, class-string> $bundles */
        $bundles = $builder->getParameter('kernel.bundles');

        return in_array($pluginClass, $bundles, true);
    }
}
