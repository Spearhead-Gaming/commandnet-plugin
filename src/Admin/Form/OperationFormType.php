<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Admin\Form;

use Forumify\Core\Form\RichTextEditorType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use MajesticDev\CommandNet\Entity\Enum\OperationStatus;
use MajesticDev\CommandNet\Entity\Enum\OperationType;
use MajesticDev\CommandNet\Entity\Operation;
use MajesticDev\CommandNet\Entity\Unit;

/**
 * Named OperationFormType, not OperationType, because MajesticDev\CommandNet\Entity\Enum\OperationType
 * already claims that short name - PHP forbids a `use` import and a class declared in the
 * same file from sharing a name, even across different namespaces.
 *
 * @extends AbstractType<Operation>
 */
class OperationFormType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Operation::class,
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class)
            ->add('type', EnumType::class, [
                'class' => OperationType::class,
                'choice_label' => fn (OperationType $t) => $t->label(),
            ])
            ->add('content', RichTextEditorType::class, [
                'label' => 'OPORD / Description',
                'required' => false,
            ])
            ->add('startDateTime', DateTimeType::class, [
                'widget' => 'single_text',
            ])
            ->add('endDateTime', DateTimeType::class, [
                'widget' => 'single_text',
                'required' => false,
            ])
            ->add('location', TextType::class, [
                'required' => false,
                'help' => 'Server name, map, or physical location.',
            ])
            ->add('unit', EntityType::class, [
                'class' => Unit::class,
                'required' => false,
                'placeholder' => 'Whole community',
                'choice_label' => 'name',
            ])
            ->add('status', EnumType::class, [
                'class' => OperationStatus::class,
                'choice_label' => fn (OperationStatus $s) => $s->label(),
            ])
        ;
    }
}
