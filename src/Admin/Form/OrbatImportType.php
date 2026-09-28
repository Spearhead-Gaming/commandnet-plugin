<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Admin\Form;

use Doctrine\ORM\EntityRepository;
use MajesticDev\CommandNet\Entity\Unit;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @extends AbstractType<array<string, mixed>>
 */
class OrbatImportType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('parent', EntityType::class, [
                'class' => Unit::class,
                'required' => false,
                'placeholder' => 'None (import as top-level units)',
                'choice_label' => 'name',
                'query_builder' => fn (EntityRepository $er) => $er->createQueryBuilder('u')->orderBy('u.name', 'ASC'),
                'help' => 'The outline\'s top-level line(s) become children of this unit.',
            ])
            ->add('outline', TextareaType::class, [
                'constraints' => [new Assert\NotBlank()],
                'attr' => ['rows' => 20, 'style' => 'font-family: monospace'],
                'help' => 'One unit per line, indented 2 spaces per level. A line starting with "+ " creates a squad/team instead of a unit; a line starting with "= " links a position; a line starting with "@" expands a standard set, e.g. "@Team" or "@Squad"; a line starting with "#" is a comment and is ignored.',
            ])
        ;
    }
}
