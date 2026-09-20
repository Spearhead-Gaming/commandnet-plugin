<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * @extends AbstractType<array<string, mixed>>
 */
class CommandNetPagePayloadType extends AbstractType
{
    public const PAGES = [
        'Roster' => 'command_net_roster',
        'Operations' => 'command_net_operations',
        'Org Chart' => 'command_net_units',
        'Attendance' => 'command_net_attendance',
        'Qualifications' => 'command_net_qualifications',
        'Enlist' => 'command_net_enlist',
        'Forms' => 'command_net_forms',
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('pages', ChoiceType::class, [
            'choices' => self::PAGES,
            'multiple' => true,
            'expanded' => true,
            'label' => 'Pages',
            'help' => 'Check every page this menu item should link to.',
        ]);
    }
}
