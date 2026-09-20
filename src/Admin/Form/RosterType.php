<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Admin\Form;

use Doctrine\ORM\EntityRepository;
use MajesticDev\CommandNet\Entity\Roster;
use MajesticDev\CommandNet\Entity\Unit;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @extends AbstractType<Roster>
 */
class RosterType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Roster::class,
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
            ])
            ->add('units', EntityType::class, [
                'class' => Unit::class,
                'multiple' => true,
                'required' => false,
                'choice_label' => 'name',
                'query_builder' => fn (EntityRepository $er) => $er->createQueryBuilder('u')->orderBy('u.position', 'ASC'),
                'help' => 'Soldiers whose primary assignment is one of these units are listed on this roster.',
            ])
        ;
    }
}
