<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Form;

use Forumify\Admin\Form\MenuItemType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

/**
 * Forumify's own MenuItemType humanizes a menu type's dropdown label by title-casing just
 * the first letter of the whole string (u($type)->title(), not title(true)) - so our
 * 'command_net' type shows up as "Command net" instead of "Command Net". Rather than patch
 * that vendor file, this extension fixes up just that one choice's label after the view is
 * built, leaving every other type's label exactly as Forumify computed it.
 */
class MenuItemTypeLabelExtension extends AbstractTypeExtension
{
    private const LABEL_OVERRIDES = [
        'command_net' => 'Command Net',
    ];

    public static function getExtendedTypes(): iterable
    {
        return [MenuItemType::class];
    }

    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        if (!isset($view['type'])) {
            return;
        }

        foreach ($view['type']->vars['choices'] as $choiceView) {
            if (isset(self::LABEL_OVERRIDES[$choiceView->value])) {
                $choiceView->label = self::LABEL_OVERRIDES[$choiceView->value];
            }
        }
    }
}
