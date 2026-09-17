<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\MenuBuilder;

use Forumify\Core\Entity\MenuItem;
use Forumify\Core\MenuBuilder\MenuType\AbstractMenuType;
use MajesticDev\CommandNet\Form\CommandNetPagePayloadType;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Twig\Environment;

/**
 * A single "Command Net" menu item covering every page in this plugin: the admin checks
 * which pages (Roster, Operations, ...) it should link to, and it renders as one dropdown
 * of just those pages - the same "one item, one page per toggle" shape MILHQ's own Menu
 * Builder integration uses, rather than making the admin create a separate Route-typed menu
 * item (and a parent Collection item) for every page individually.
 */
class CommandNetMenuType extends AbstractMenuType
{
    private Environment $twig;

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[Required]
    public function setTwig(Environment $twig): void
    {
        $this->twig = $twig;
    }

    public function getType(): string
    {
        return 'command_net';
    }

    public function getPayloadFormType(): ?string
    {
        return CommandNetPagePayloadType::class;
    }

    protected function render(MenuItem $item): string
    {
        $selected = $item->getPayloadValue('pages') ?? [];
        $inner = '';
        foreach (CommandNetPagePayloadType::PAGES as $label => $route) {
            if (!in_array($route, $selected, true)) {
                continue;
            }

            $inner .= $this->twig->render('@Forumify/frontend/menu/url.html.twig', [
                'url' => $this->urlGenerator->generate($route),
                'label' => $label,
                'external' => false,
            ]);
        }

        if ($inner === '') {
            return '';
        }

        return $this->twig->render('@Forumify/frontend/menu/collection.html.twig', [
            'name' => $item->getName(),
            'placement' => $item->getParent() === null ? 'bottom-start' : 'right-start',
            'inner' => $inner,
        ]);
    }
}
