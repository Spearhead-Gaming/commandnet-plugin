<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Admin\Form;

use Forumify\Core\Entity\Role;
use MajesticDev\CommandNet\Entity\Specialty;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<Specialty>
 */
class SpecialtyType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Specialty::class,
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'help' => 'e.g. "Combat Medic", "Radio Operator".',
            ])
            ->add('abbreviation', TextType::class, [
                'help' => 'Shown on the roster, e.g. "MED".',
            ])
            ->add('description', TextareaType::class, [
                'required' => false,
                'empty_data' => '',
            ])
            ->add('role', EntityType::class, [
                'class' => Role::class,
                'required' => false,
                'placeholder' => 'None',
                'choice_label' => 'title',
                'label' => 'Discord/forumify role',
                'help' => 'Granted to a soldier while this is their specialty. Map it to a Discord role in the connection settings of the Discord plugin to sync Discord automatically.',
            ])
        ;
    }
}
