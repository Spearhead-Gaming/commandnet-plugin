<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Form;

use MajesticDev\CommandNet\Entity\Position;
use MajesticDev\CommandNet\Entity\Squad;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * The Squad counterpart of UnitSelfServiceType - even smaller, since a squad/team has no
 * commander, abbreviation, insignia or any of Unit's other command-specific fields, just a
 * name and the billets held within it.
 *
 * @extends AbstractType<Squad>
 */
class SquadSelfServiceType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Squad::class]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('name', TextType::class);

        // Same unmapped-field trick as UnitSelfServiceType - see MySquadController for the sync.
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $squad = $event->getData();
            $current = $squad instanceof Squad
                ? implode("\n", array_map(static fn (Position $p) => $p->getTitle(), $squad->getPositions()->toArray()))
                : '';

            $event->getForm()->add('positions', TextareaType::class, [
                'mapped' => false,
                'required' => false,
                'data' => $current,
                'label' => 'Positions in this squad/team',
                'help' => 'One billet per line - e.g. squad lead or team lead titles someone can be assigned to within this squad/team.',
                'attr' => ['rows' => 6],
            ]);
        });
    }
}
