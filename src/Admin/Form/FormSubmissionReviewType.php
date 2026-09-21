<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Admin\Form;

use MajesticDev\CommandNet\Entity\FormSubmission;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Answers are shown read-only; the only action is deciding. The decision and note are
 * unmapped and applied by FormSubmissionService, which also notifies the submitter.
 *
 * @extends AbstractType<FormSubmission>
 */
class FormSubmissionReviewType extends AbstractType
{
    public const string ACCEPT = 'accept';
    public const string DECLINE = 'decline';

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => FormSubmission::class,
            'pending' => false,
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('user', TextType::class, [
                'property_path' => 'user.displayName',
                'label' => 'Submitted by',
                'disabled' => true,
            ])
            ->add('formName', TextType::class, ['label' => 'Form', 'disabled' => true])
            ->add('answersAsText', TextareaType::class, [
                'label' => 'Answers',
                'disabled' => true,
                'attr' => ['rows' => 10],
            ])
        ;

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
                'label' => 'Note to the submitter',
                'help' => 'Optional. Included in the notification they receive.',
                'constraints' => [new Assert\Length(max: 2000)],
            ])
        ;
    }
}
