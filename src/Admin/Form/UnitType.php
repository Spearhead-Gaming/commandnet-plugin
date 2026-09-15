<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Admin\Form;

use Doctrine\ORM\EntityRepository;
use Forumify\Core\Form\UploadType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Entity\Unit;

/**
 * @extends AbstractType<Unit>
 */
class UnitType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Unit::class,
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class)
            ->add('abbreviation', TextType::class, [
                'help' => 'Short tag shown in rosters and breadcrumbs, e.g. "A Co".',
            ])
            ->add('description', TextareaType::class, [
                'required' => false,
                'empty_data' => '',
            ])
            ->add('insignia', UploadType::class, [
                'label' => 'Unit Insignia',
                'required' => false,
                'help' => 'Recommended size is 256x256.',
                'filesystem' => 'asset.storage',
                'asset_package' => 'forumify.asset',
                'accept' => 'image/*',
                'file_constraints' => [new Assert\Image(maxSize: '5M')],
            ])
            ->add('commander', EntityType::class, [
                'class' => SoldierProfile::class,
                'required' => false,
                'placeholder' => 'Vacant',
                'choice_label' => fn (SoldierProfile $s) => (string)$s,
            ])
        ;

        // Added in an event listener rather than buildForm() directly so we have access
        // to the entity being edited, letting us exclude it (and keep the dropdown fresh).
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $unit = $event->getData();
            $excludeId = $unit instanceof Unit ? $unit->getId() : null;

            $event->getForm()->add('parent', EntityType::class, [
                'class' => Unit::class,
                'required' => false,
                'placeholder' => 'None (top-level unit)',
                'choice_label' => 'name',
                'query_builder' => function (EntityRepository $er) use ($excludeId) {
                    $qb = $er->createQueryBuilder('u')->orderBy('u.name', 'ASC');
                    if ($excludeId !== null) {
                        $qb->andWhere('u.id != :excludeId')->setParameter('excludeId', $excludeId);
                    }
                    return $qb;
                },
            ]);
        });
    }
}
