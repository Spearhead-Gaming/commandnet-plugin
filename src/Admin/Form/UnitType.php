<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Admin\Form;

use Doctrine\ORM\EntityRepository;
use Forumify\Core\Entity\Role;
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
use MajesticDev\CommandNet\Repository\UnitRepository;

/**
 * @extends AbstractType<Unit>
 */
class UnitType extends AbstractType
{
    public function __construct(private readonly UnitRepository $unitRepository)
    {
    }

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
            ->add('role', EntityType::class, [
                'class' => Role::class,
                'required' => false,
                'placeholder' => 'None',
                'choice_label' => 'title',
                'label' => 'Discord/forumify role',
                'help' => 'Granted to a soldier while this is their primary unit. Map it to a Discord role in the Discord plugin\'s own connection settings to sync Discord roles automatically.',
            ])
            ->add('discordGuildId', TextType::class, [
                'label' => 'Discord Server (Guild) ID',
                'required' => false,
                'help' => 'Leave blank to use the community server. Set this to target this unit\'s own private Discord server for AWOL/operation notifications - must match a connection configured in the Discord plugin.',
            ])
        ;

        // Added in an event listener rather than buildForm() directly so we have access
        // to the entity being edited, letting us exclude it (and keep the dropdown fresh).
        // Excludes the unit's own descendants too, not just itself - picking a child as a
        // unit's own parent would otherwise create a cycle the tree isn't built to handle.
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $unit = $event->getData();
            $excludeIds = [];
            if ($unit instanceof Unit && $unit->getId() !== null) {
                $excludeIds[] = $unit->getId();
                array_push($excludeIds, ...$this->unitRepository->getDescendantIds($unit));
            }

            $event->getForm()->add('parent', EntityType::class, [
                'class' => Unit::class,
                'required' => false,
                'placeholder' => 'None (top-level unit)',
                'choice_label' => 'name',
                'query_builder' => function (EntityRepository $er) use ($excludeIds) {
                    $qb = $er->createQueryBuilder('u')->orderBy('u.name', 'ASC');
                    if ($excludeIds !== []) {
                        $qb->andWhere('u.id NOT IN (:excludeIds)')->setParameter('excludeIds', $excludeIds);
                    }
                    return $qb;
                },
            ]);
        });
    }
}
