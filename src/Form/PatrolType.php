<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Form;

use Forumify\Core\Form\RichTextEditorType;
use MajesticDev\CommandNet\Entity\Deployment;
use MajesticDev\CommandNet\Entity\Operation;
use MajesticDev\CommandNet\Entity\Unit;
use MajesticDev\CommandNet\Repository\DeploymentRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The "Post a patrol" form. Deliberately small: type, leader and status are set by the
 * controller, and there is no calendar field so patrols stay off synced calendars.
 *
 * @extends AbstractType<Operation>
 */
class PatrolType extends AbstractType
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
            ->add('title', TextType::class, [
                'constraints' => [new Assert\NotBlank()],
            ])
            ->add('startDateTime', DateTimeType::class, [
                'label' => 'Starts',
                'widget' => 'single_text',
            ])
            ->add('endDateTime', DateTimeType::class, [
                'label' => 'Ends',
                'widget' => 'single_text',
                'required' => false,
                'help' => 'Your AAR is due 24 hours after this. Leave blank to count from the start time.',
                'constraints' => [new Assert\GreaterThan(propertyPath: 'parent.all[startDateTime].data', message: 'The patrol must end after it starts.')],
            ])
            ->add('location', TextType::class, [
                'label' => 'Area',
                'required' => false,
                'help' => 'Server, map or area of operations.',
            ])
            ->add('content', RichTextEditorType::class, [
                'label' => 'Plan',
                'required' => false,
            ])
            ->add('unit', EntityType::class, [
                'class' => Unit::class,
                'required' => false,
                'placeholder' => 'Open to everyone',
                'choice_label' => 'name',
            ])
            ->add('deployment', EntityType::class, [
                'class' => Deployment::class,
                'required' => false,
                'placeholder' => 'None',
                'choice_label' => 'name',
                'query_builder' => static fn (DeploymentRepository $repository) => $repository
                    ->createQueryBuilder('deployment')
                    ->orderBy('deployment.startDate', 'DESC'),
                'help' => 'The monthly deployment this patrol belongs to, if any.',
            ])
            ->add('maxParticipants', IntegerType::class, [
                'label' => 'Maximum joiners',
                'required' => false,
                'help' => 'Leave blank for no limit.',
                'constraints' => [new Assert\Positive()],
            ])
        ;
    }
}
