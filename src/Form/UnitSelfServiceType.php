<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Form;

use MajesticDev\CommandNet\Entity\Position;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Entity\Unit;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A deliberately smaller version of the admin UnitType for self-service unit management
 * (see UnitAuthorizationChecker): name, abbreviation, description and commander only. Parent,
 * insignia, the Discord/forumify role grant, vehicles, ORBAT export size/type and the Discord
 * guild override stay admin-only settings - a commander shapes their own sub-tree's structure,
 * not the community-wide/Discord-facing configuration around it.
 *
 * @extends AbstractType<Unit>
 */
class UnitSelfServiceType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Unit::class]);
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
            ->add('commander', EntityType::class, [
                'class' => SoldierProfile::class,
                'required' => false,
                'placeholder' => 'Vacant',
                'choice_label' => fn (SoldierProfile $s) => (string)$s,
            ])
        ;

        // Unmapped: Unit::positions is a plain ManyToMany with no setter for the whole
        // collection, so the controller reads this field itself and syncs it via
        // PositionCatalog - see MyUnitController. Pre-filled here (rather than left blank
        // and handled entirely in the controller) so editing an existing unit shows what it
        // already has instead of looking like a fresh empty field every time.
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $unit = $event->getData();
            $current = $unit instanceof Unit
                ? implode("\n", array_map(static fn (Position $p) => $p->getTitle(), $unit->getPositions()->toArray()))
                : '';

            $event->getForm()->add('positions', TextareaType::class, [
                'mapped' => false,
                'required' => false,
                'data' => $current,
                'label' => 'Positions in this unit',
                'help' => 'One billet per line - e.g. leadership, squad lead, or team lead titles someone can be assigned to within this unit.',
                'attr' => ['rows' => 6],
            ]);
        });
    }
}
