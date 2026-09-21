<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Form;

use DateTime;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use MajesticDev\CommandNet\Entity\Assignment;
use MajesticDev\CommandNet\Entity\Position;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Entity\Document;
use MajesticDev\CommandNet\Entity\Unit;

/**
 * @extends AbstractType<Assignment>
 */
class CreateAssignmentType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Assignment::class,
            // Same construction trick as AwardSoldierType: the soldier has no setter,
            // so build the entity once "unit" has been submitted.
            'empty_data' => function (FormInterface $form): Assignment {
                /** @var SoldierProfile $soldier */
                $soldier = $form->getConfig()->getOption('soldier');
                /** @var Unit $unit */
                $unit = $form->get('unit')->getData();
                return new Assignment($soldier, $unit);
            },
        ]);
        $resolver->setRequired('soldier');
        $resolver->setAllowedTypes('soldier', SoldierProfile::class);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('document', EntityType::class, [
                'class' => Document::class,
                'mapped' => false,
                'required' => false,
                'placeholder' => 'None',
                'choice_label' => 'name',
                'help' => 'Optional. Shown with this entry on the personnel file, filled in for the soldier.',
            ])
            ->add('unit', EntityType::class, [
                'class' => Unit::class,
                'choice_label' => fn (Unit $u) => $u->getName(),
                'query_builder' => fn (EntityRepository $er) => $er->createQueryBuilder('u')->orderBy('u.name', 'ASC'),
            ])
            ->add('position', EntityType::class, [
                'class' => Position::class,
                'required' => false,
                'placeholder' => 'None',
                'choice_label' => fn (Position $p) => $p->getTitle(),
                'query_builder' => fn (EntityRepository $er) => $er->createQueryBuilder('p')->orderBy('p.title', 'ASC'),
            ])
            ->add('isPrimary', CheckboxType::class, [
                'label' => 'Primary assignment',
                'required' => false,
                'help' => 'Ends the soldier\'s current primary assignment, if any.',
            ])
            ->add('startDate', DateType::class, [
                'label' => 'Start date',
                'widget' => 'single_text',
                'data' => new DateTime('today'),
                'constraints' => [new Assert\NotBlank()],
            ])
            ->add('endDate', DateType::class, [
                'label' => 'End date',
                'required' => false,
                'widget' => 'single_text',
            ])
        ;
    }
}
