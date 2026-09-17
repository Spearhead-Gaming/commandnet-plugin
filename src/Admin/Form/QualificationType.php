<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Admin\Form;

use Forumify\Core\Form\UploadType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use MajesticDev\CommandNet\Entity\Enum\QualificationTier;
use MajesticDev\CommandNet\Entity\Qualification;
use MajesticDev\CommandNet\Entity\Unit;

/**
 * @extends AbstractType<Qualification>
 */
class QualificationType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Qualification::class,
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'help' => 'e.g. "Combat Medic", "Jumpmaster".',
            ])
            ->add('description', TextareaType::class, [
                'required' => false,
                'empty_data' => '',
            ])
            ->add('icon', UploadType::class, [
                'label' => 'Badge Icon',
                'required' => false,
                'help' => 'Recommended size is 64x64.',
                'filesystem' => 'asset.storage',
                'asset_package' => 'forumify.asset',
                'accept' => 'image/*',
                'file_constraints' => [new Assert\Image(maxSize: '5M')],
            ])
            ->add('tier', EnumType::class, [
                'class' => QualificationTier::class,
                'choice_label' => fn (QualificationTier $t) => $t->label(),
                'required' => false,
                'placeholder' => 'Not shown on the qualifications board',
            ])
            ->add('units', EntityType::class, [
                'class' => Unit::class,
                'multiple' => true,
                'required' => false,
                'choice_label' => 'name',
                'label' => 'Unit-specific',
                'help' => 'Leave empty to make this qualification available to every unit.',
            ])
        ;
    }
}
