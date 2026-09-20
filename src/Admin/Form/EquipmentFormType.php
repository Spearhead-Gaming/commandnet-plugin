<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Admin\Form;

use MajesticDev\CommandNet\Entity\Enum\EquipmentType;
use MajesticDev\CommandNet\Entity\Equipment;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<Equipment>
 */
class EquipmentFormType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Equipment::class,
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'help' => 'e.g. "M4A1 Carbine", "M9 Pistol", "HMMWV".',
            ])
            ->add('type', EnumType::class, [
                'class' => EquipmentType::class,
                'choice_label' => fn (EquipmentType $type) => $type->label(),
            ])
        ;
    }
}
