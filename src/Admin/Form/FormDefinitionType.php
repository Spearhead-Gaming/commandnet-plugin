<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Admin\Form;

use InvalidArgumentException;
use MajesticDev\CommandNet\Entity\FormDefinition;
use MajesticDev\CommandNet\Service\FormSchema;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * @extends AbstractType<FormDefinition>
 */
class FormDefinitionType extends AbstractType
{
    public function __construct(private readonly FormSchema $schema)
    {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => FormDefinition::class,
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 150)],
            ])
            ->add('description', TextareaType::class, [
                'required' => false,
                'empty_data' => '',
                'help' => 'Shown above the form.',
            ])
            ->add('enabled', CheckboxType::class, [
                'required' => false,
                'label' => 'Open for submissions',
            ])
            ->add('fieldList', TextareaType::class, [
                'label' => 'Fields',
                'attr' => ['rows' => 12],
                'help' => 'One field per line: type | Label | required | options. Types: text, textarea, number, boolean, date, select. '
                    . 'Only select fields take options, separated by commas. The third part is required or optional (default optional). '
                    . 'Example: select | Branch | required | Army, Navy, Air Force. Lines starting with # are ignored.',
                'constraints' => [
                    new Assert\Callback(function (mixed $value, ExecutionContextInterface $context): void {
                        try {
                            $this->schema->parse((string)$value);
                        } catch (InvalidArgumentException $e) {
                            $context->buildViolation($e->getMessage())->addViolation();
                        }
                    }),
                ],
            ])
        ;
    }
}
