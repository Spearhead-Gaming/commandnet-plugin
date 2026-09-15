<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Admin\Form;

use Forumify\Core\Form\UploadType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
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
