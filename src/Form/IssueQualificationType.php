<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Form;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use MajesticDev\CommandNet\Entity\Qualification;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Entity\Document;
use MajesticDev\CommandNet\Entity\SoldierQualification;

/**
 * @extends AbstractType<SoldierQualification>
 */
class IssueQualificationType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SoldierQualification::class,
            // Same construction trick as AwardSoldierType: neither the soldier nor the
            // qualification has a setter, so build the entity once "qualification" is submitted.
            'empty_data' => function (FormInterface $form): SoldierQualification {
                /** @var SoldierProfile $soldier */
                $soldier = $form->getConfig()->getOption('soldier');
                /** @var Qualification $qualification */
                $qualification = $form->get('qualification')->getData();
                return new SoldierQualification($soldier, $qualification);
            },
        ]);
        $resolver->setRequired('soldier');
        $resolver->setAllowedTypes('soldier', SoldierProfile::class);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('qualification', EntityType::class, [
                'class' => Qualification::class,
                'choice_label' => fn (Qualification $q) => $q->getName(),
            ])
            ->add('dateEarned', DateType::class, [
                'label' => 'Date earned',
                'widget' => 'single_text',
            ])
            ->add('document', EntityType::class, [
                'class' => Document::class,
                'mapped' => false,
                'required' => false,
                'placeholder' => 'None',
                'choice_label' => 'name',
                'help' => 'Optional. Shown with this entry on the personnel file, filled in for the soldier.',
            ])
        ;
    }
}
