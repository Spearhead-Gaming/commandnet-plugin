<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The self-service counterpart of the admin OrbatImportType, minus its "parent" field - a
 * commander using this always imports into the unit they're already managing, never anywhere
 * else in the tree, so that choice isn't exposed to them at all.
 *
 * @extends AbstractType<array<string, mixed>>
 */
class OrbatOutlineType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('outline', TextareaType::class, [
            'constraints' => [new Assert\NotBlank()],
            'attr' => ['rows' => 16, 'style' => 'font-family: monospace'],
            'help' => 'One unit per line, indented 2 spaces per level - these become children of this unit. A line starting with "+ " creates a squad/team instead of a unit; a line starting with "= " links a position; a line starting with "@" expands a standard set, e.g. "@Team" or "@Squad"; a line starting with "#" is a comment and is ignored.',
        ]);
    }
}
