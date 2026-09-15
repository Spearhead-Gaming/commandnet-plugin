<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Form;

use Forumify\Core\Form\RichTextEditorType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use MajesticDev\CommandNet\Entity\OperationAAR;

/**
 * @extends AbstractType<OperationAAR>
 */
class OperationAarType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => OperationAAR::class,
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('summary', RichTextEditorType::class, [
                'label' => 'Summary',
            ])
            ->add('objectivesMet', ChoiceType::class, [
                'label' => 'Objectives Met?',
                'required' => false,
                'placeholder' => 'Not specified',
                'choices' => [
                    'Yes' => true,
                    'No' => false,
                ],
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Additional Notes',
                'required' => false,
                'empty_data' => '',
            ])
        ;
    }
}
