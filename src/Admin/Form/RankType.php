<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Admin\Form;

use Forumify\Core\Entity\Role;
use Forumify\Core\Form\UploadType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use MajesticDev\CommandNet\Entity\Qualification;
use MajesticDev\CommandNet\Entity\Rank;

/**
 * @extends AbstractType<Rank>
 */
class RankType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Rank::class,
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class)
            ->add('abbreviation', TextType::class, [
                'help' => 'Shown in rosters and on the org chart, e.g. "SGT".',
            ])
            ->add('payGrade', TextType::class, [
                'required' => false,
                'help' => 'Optional, e.g. "E-5".',
            ])
            ->add('minTimeInGradeDays', IntegerType::class, [
                'required' => false,
                'label' => 'Minimum time in previous rank (days)',
                'help' => 'To be promoted into this rank. Leave empty for no minimum.',
                'attr' => ['min' => 0],
            ])
            ->add('requiredQualifications', EntityType::class, [
                'class' => Qualification::class,
                'choice_label' => 'name',
                'multiple' => true,
                'required' => false,
                'help' => 'Qualifications a soldier must hold to be promoted into this rank.',
            ])
            ->add('role', EntityType::class, [
                'class' => Role::class,
                'required' => false,
                'placeholder' => 'None',
                'choice_label' => 'title',
                'label' => 'Discord/forumify role',
                'help' => 'Granted to a soldier while this is their rank, and removed when they change rank. Map it to a Discord role in the Discord plugin's own connection settings to sync Discord automatically.',
            ])
            ->add('insignia', UploadType::class, [
                'label' => 'Insignia',
                'required' => false,
                'help' => 'Recommended size is 128x128.',
                'filesystem' => 'asset.storage',
                'asset_package' => 'forumify.asset',
                'accept' => 'image/*',
                'file_constraints' => [new Assert\Image(maxSize: '5M')],
            ])
        ;
    }
}
