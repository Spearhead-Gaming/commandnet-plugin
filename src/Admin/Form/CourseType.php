<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Admin\Form;

use Doctrine\ORM\EntityRepository;
use MajesticDev\CommandNet\Entity\Course;
use MajesticDev\CommandNet\Entity\Qualification;
use MajesticDev\CommandNet\Entity\Rank;
use MajesticDev\CommandNet\Service\RankSettings;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @extends AbstractType<Course>
 */
class CourseType extends AbstractType
{
    public function __construct(private readonly RankSettings $rankSettings)
    {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Course::class,
            // The course being edited, kept out of its own prerequisites.
            'current' => null,
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $current = $options['current'];
        $builder
            ->add('name', TextType::class, [
                'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 150)],
            ])
            ->add('description', TextareaType::class, [
                'empty_data' => '',
            ])
            ->add('prerequisites', EntityType::class, [
                'class' => Course::class,
                'multiple' => true,
                'required' => false,
                'choice_label' => 'name',
                'query_builder' => fn (EntityRepository $er) => $current instanceof Course
                    ? $er->createQueryBuilder('c')->where('c != :current')->setParameter('current', $current)->orderBy('c.name', 'ASC')
                    : $er->createQueryBuilder('c')->orderBy('c.name', 'ASC'),
                'help' => 'Courses a soldier must have passed before enrolling.',
            ])
            ->add('qualifications', EntityType::class, [
                'class' => Qualification::class,
                'multiple' => true,
                'required' => false,
                'choice_label' => 'name',
                'help' => 'Granted to every student who passes a class of this course.',
            ])
        ;

        if ($this->rankSettings->isEnabled()) {
            $builder->add('minimumRank', EntityType::class, [
                'class' => Rank::class,
                'required' => false,
                'placeholder' => 'No minimum',
                'choice_label' => 'name',
                'query_builder' => fn (EntityRepository $er) => $er->createQueryBuilder('r')->orderBy('r.position', 'ASC'),
            ]);
        }
    }
}
