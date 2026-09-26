<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Form;

use Forumify\Core\Form\RichTextEditorType;
use Forumify\Core\Form\UploadType;
use MajesticDev\CommandNet\Entity\OperationAAR;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Patrols follow the community's AAR template - tasking, callsigns, casualties, the report - and
 * must include at least one map and one intel image. Every other event keeps the original short
 * form, so the "patrol" option is what switches the template on.
 *
 * @extends AbstractType<OperationAAR>
 */
class OperationAarType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => OperationAAR::class,
            'patrol' => false,
        ]);
        $resolver->setAllowedTypes('patrol', 'bool');
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if ($options['patrol']) {
            $this->addTemplateFields($builder);
        }

        $builder
            ->add('summary', RichTextEditorType::class, [
                'label' => $options['patrol'] ? 'Report' : 'Summary',
                'help' => $options['patrol']
                    ? 'Use grid coordinates when referring to locations on the map. Be descriptive but concise: you are writing a report, not a story.'
                    : null,
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
        ;

        if ($options['patrol']) {
            $this
                ->addImages($builder, 'mapImages', 'Map', 'A screenshot of the map. Add as many as you need.')
                ->addImages($builder, 'intelImages', 'Intel', 'Intel images and any other relevant media.');
        }

        $builder->add('notes', TextareaType::class, [
            'label' => 'Additional Notes',
            'required' => false,
            'empty_data' => '',
        ]);
    }

    /**
     * @param FormBuilderInterface<OperationAAR|null> $builder
     */
    private function addTemplateFields(FormBuilderInterface $builder): void
    {
        $builder
            ->add('tasking', TextType::class, [
                'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 255)],
            ])
            ->add('callsigns', TextareaType::class, [
                'help' => 'Filled in from the members who joined the patrol. Correct it if it is wrong.',
                'constraints' => [new Assert\NotBlank()],
            ])
            ->add('friendlyCasualties', TextType::class, [
                'label' => 'FKIA / FWIA / FMIA',
                'help' => 'Friendly killed, wounded and missing in action, e.g. "0 / 1 / 0".',
                'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 100)],
            ])
            ->add('enemyKia', TextType::class, [
                'label' => 'EKIA',
                'help' => 'Enemy killed in action.',
                'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 100)],
            ])
        ;
    }

    /**
     * @param FormBuilderInterface<OperationAAR|null> $builder
     */
    private function addImages(FormBuilderInterface $builder, string $field, string $label, string $help): self
    {
        $builder->add($field, UploadType::class, [
            'label' => $label,
            'help' => $help,
            'multiple' => true,
            'required' => true,
            'filesystem' => 'asset.storage',
            'asset_package' => 'forumify.asset',
            'accept' => 'image/*',
            'file_constraints' => [new Assert\Image(maxSize: '8M')],
        ]);

        return $this;
    }
}
