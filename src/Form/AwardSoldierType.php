<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Form;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use MajesticDev\CommandNet\Entity\Award;
use MajesticDev\CommandNet\Entity\SoldierAward;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Entity\Document;

/**
 * @extends AbstractType<SoldierAward>
 */
class AwardSoldierType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SoldierAward::class,
            // SoldierAward's constructor requires both the soldier and the award, and
            // neither has a setter (an award, once issued, isn't meant to be reassigned).
            // Build it ourselves once the "award" field has been submitted, the same way
            // SoldierProfileType builds its entity from the submitted "user".
            'empty_data' => function (FormInterface $form): SoldierAward {
                /** @var SoldierProfile $soldier */
                $soldier = $form->getConfig()->getOption('soldier');
                /** @var Award $award */
                $award = $form->get('award')->getData();
                return new SoldierAward($soldier, $award);
            },
        ]);
        $resolver->setRequired('soldier');
        $resolver->setAllowedTypes('soldier', SoldierProfile::class);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('award', EntityType::class, [
                'class' => Award::class,
                'choice_label' => fn (Award $a) => $a->getName(),
            ])
            ->add('dateAwarded', DateType::class, [
                'label' => 'Date awarded',
                'widget' => 'single_text',
            ])
            ->add('citation', TextareaType::class, [
                'label' => 'Citation',
                'required' => false,
                'empty_data' => '',
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
