<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\MenuBuilder;

use Forumify\Core\Entity\MenuItem;
use Forumify\Core\MenuBuilder\MenuType\UrlMenuType;
use MajesticDev\CommandNet\Form\CommandNetPagePayloadType;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * A friendlier alternative to the generic "Route" menu type for this plugin's own pages:
 * admins pick "Roster" / "Operations" / etc. by name instead of finding the right
 * command_net_* entry in a dropdown of every route on the site. Only lists pages that make
 * sense as a static menu link - command_net_roster_profile/operation_detail need a
 * {username}/{id} and were left out of CommandNetPagePayloadType::PAGES for that reason.
 */
class CommandNetMenuType extends UrlMenuType
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function getType(): string
    {
        return 'command_net_page';
    }

    public function getPayloadFormType(): ?string
    {
        return CommandNetPagePayloadType::class;
    }

    protected function getUrl(MenuItem $item): string
    {
        $route = $item->getPayloadValue('route');
        if (!in_array($route, CommandNetPagePayloadType::PAGES, true)) {
            return '';
        }

        return $this->urlGenerator->generate($route);
    }
}
