<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Admin\Form;

use MajesticDev\CommandNet\Entity\Course;
use MajesticDev\CommandNet\Entity\CourseClass;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @extends AbstractType<CourseClass>
 */
class CourseClassType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CourseClass::class,
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('course', EntityType::class, [
                'class' => Course::class,
                'choice_label' => 'name',
            ])
            ->add('startsAt', DateTimeType::class, [
                'widget' => 'single_text',
                'label' => 'Starts',
            ])
            ->add('endsAt', DateTimeType::class, [
                'widget' => 'single_text',
                'required' => false,
                'label' => 'Ends',
            ])
            ->add('studentSlots', IntegerType::class, [
                'required' => false,
                'label' => 'Places',
                'help' => 'Leave empty for no limit.',
                'constraints' => [new Assert\Positive()],
            ])
            ->add('instructor', EntityType::class, [
                'class' => SoldierProfile::class,
                'required' => false,
                'placeholder' => 'None',
                'choice_label' => fn (SoldierProfile $soldier) => (string)$soldier,
            ])
            ->add('notes', TextareaType::class, [
                'required' => false,
                'empty_data' => '',
            ])
        ;
    }
}
