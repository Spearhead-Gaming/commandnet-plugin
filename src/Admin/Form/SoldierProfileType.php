<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Admin\Form;

use Doctrine\ORM\EntityRepository;
use Forumify\Core\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use MajesticDev\CommandNet\Entity\Enum\SoldierStatus;
use MajesticDev\CommandNet\Entity\Rank;
use MajesticDev\CommandNet\Entity\SoldierProfile;

/**
 * @extends AbstractType<SoldierProfile>
 */
class SoldierProfileType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SoldierProfile::class,
            // SoldierProfile's constructor requires a User, so we can't let the form
            // instantiate it with `new SoldierProfile()`. Build it ourselves once the
            // "user" field has been submitted.
            'empty_data' => function (FormInterface $form): SoldierProfile {
                /** @var User $user */
                $user = $form->get('user')->getData();
                return new SoldierProfile($user);
            },
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isNew = $options['data'] === null;

        $builder->add('user', EntityType::class, [
            'class' => User::class,
            'choice_label' => fn (User $u) => $u->getDisplayName() . ' (@' . $u->getUsername() . ')',
            // Once a profile exists, the linked forum account is fixed — swapping it
            // would silently orphan the old user's service history.
            'disabled' => !$isNew,
            'query_builder' => fn (EntityRepository $er) => $er->createQueryBuilder('u')->orderBy('u.displayName', 'ASC'),
        ]);

        $builder
            ->add('rank', EntityType::class, [
                'class' => Rank::class,
                'required' => false,
                'placeholder' => 'Unranked',
                'choice_label' => fn (Rank $r) => (string)$r,
                'query_builder' => fn (EntityRepository $er) => $er->createQueryBuilder('r')->orderBy('r.position', 'ASC'),
            ])
            ->add('serviceNumber', TextType::class, [
                'required' => false,
            ])
            ->add('callsign', TextType::class, [
                'required' => false,
            ])
            ->add('status', EnumType::class, [
                'class' => SoldierStatus::class,
                'choice_label' => fn (SoldierStatus $s) => $s->label(),
            ])
            ->add('enlistmentDate', DateType::class, [
                'required' => false,
                'widget' => 'single_text',
            ])
            ->add('dischargeDate', DateType::class, [
                'required' => false,
                'widget' => 'single_text',
            ])
            ->add('bio', TextareaType::class, [
                'required' => false,
                'empty_data' => '',
            ])
        ;

        // Once the entity exists, "user" is locked above — but a disabled field also
        // stops the form from submitting a value for it, which would break the
        // empty_data closure on create. This keeps the constructor happy either way.
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($isNew): void {
            if ($isNew) {
                return;
            }

            $profile = $event->getData();
            if ($profile instanceof SoldierProfile) {
                $event->getForm()->get('user')->setData($profile->getUser());
            }
        });
    }
}
