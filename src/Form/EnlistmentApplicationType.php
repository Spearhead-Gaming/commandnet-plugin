<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Form;

use Forumify\Core\Entity\User;
use MajesticDev\CommandNet\Entity\EnlistmentApplication;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @extends AbstractType<EnlistmentApplication>
 */
class EnlistmentApplicationType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => EnlistmentApplication::class,
            // The applicant has no setter on the entity, so the form builds it around them.
            'empty_data' => static fn (FormInterface $form) => new EnlistmentApplication($form->getConfig()->getOption('user')),
        ]);
        $resolver->setRequired('user');
        $resolver->setAllowedTypes('user', User::class);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('callsign', TextType::class, [
                'required' => false,
                'label' => 'Preferred callsign',
                'help' => 'Optional. You can change it later.',
                'constraints' => [new Assert\Length(max: 50)],
            ])
            ->add('steamId', TextType::class, [
                'required' => false,
                'label' => 'Steam ID',
                'help' => 'Optional. Your 17-digit SteamID64.',
                'constraints' => [new Assert\Regex(pattern: '/^\d{17}$/', message: 'A SteamID64 is 17 digits.')],
            ])
            ->add('motivation', TextareaType::class, [
                'label' => 'Why do you want to join?',
                'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 5000)],
            ])
            ->add('experience', TextareaType::class, [
                'required' => false,
                'label' => 'Previous milsim or gaming experience',
                'constraints' => [new Assert\Length(max: 5000)],
            ])
            ->add('availability', TextType::class, [
                'required' => false,
                'label' => 'Availability and time zone',
                'help' => 'For example: weekends, US Eastern.',
                'constraints' => [new Assert\Length(max: 255)],
            ])
        ;
    }
}
