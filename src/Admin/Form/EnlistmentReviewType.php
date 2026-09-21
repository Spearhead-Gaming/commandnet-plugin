<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Admin\Form;

use MajesticDev\CommandNet\Entity\EnlistmentApplication;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The applicant's answers are shown read-only; the only thing a reviewer can do here is
 * decide. The decision and note are unmapped - EnlistmentService applies them, since
 * accepting has to create the personnel file, not just flip a field.
 *
 * @extends AbstractType<EnlistmentApplication>
 */
class EnlistmentReviewType extends AbstractType
{
    public const string ACCEPT = 'accept';
    public const string DECLINE = 'decline';

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => EnlistmentApplication::class,
            'pending' => false,
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        foreach (['callsign' => 'Callsign', 'steamId' => 'Steam ID', 'availability' => 'Availability'] as $field => $label) {
            $builder->add($field, TextType::class, ['label' => $label, 'disabled' => true, 'required' => false]);
        }
        foreach (['motivation' => 'Why they want to join', 'experience' => 'Experience'] as $field => $label) {
            $builder->add($field, TextareaType::class, ['label' => $label, 'disabled' => true, 'required' => false]);
        }

        if (!$options['pending']) {
            $builder->add('decisionNote', TextareaType::class, ['label' => 'Decision note', 'disabled' => true, 'required' => false]);
            return;
        }

        $builder
            ->add('decision', ChoiceType::class, [
                'mapped' => false,
                'expanded' => true,
                'label' => 'Decision',
                'choices' => ['Accept' => self::ACCEPT, 'Decline' => self::DECLINE],
                'constraints' => [new Assert\NotBlank()],
            ])
            ->add('note', TextareaType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Note to the applicant',
                'help' => 'Optional. Included in the notification they receive.',
                'constraints' => [new Assert\Length(max: 2000)],
            ])
        ;
    }
}
