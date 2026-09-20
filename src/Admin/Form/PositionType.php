<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Admin\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use MajesticDev\CommandNet\Entity\Position;
use MajesticDev\CommandNet\Entity\Equipment;
use MajesticDev\CommandNet\Entity\Enum\EquipmentType;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;

/**
 * @extends AbstractType<Position>
 */
class PositionType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Position::class,
        ]);
    }

    /**
     * Query builder callback listing only equipment of one type, alphabetically.
     */
    private static function ofType(EquipmentType $type): callable
    {
        return static fn (EntityRepository $er) => $er->createQueryBuilder('e')
            ->where('e.type = :type')
            ->setParameter('type', $type)
            ->orderBy('e.name', 'ASC');
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'help' => 'e.g. "Squad Leader", "Combat Medic".',
            ])
            ->add('description', TextareaType::class, [
                'required' => false,
                'empty_data' => '',
            ])
            ->add('primaryWeapons', EntityType::class, [
                'class' => Equipment::class,
                'multiple' => true,
                'required' => false,
                'choice_label' => 'name',
                'query_builder' => self::ofType(EquipmentType::PRIMARY_WEAPON),
                'help' => 'Primary weapons the holder of this position may use.',
            ])
            ->add('secondaryWeapons', EntityType::class, [
                'class' => Equipment::class,
                'multiple' => true,
                'required' => false,
                'choice_label' => 'name',
                'query_builder' => self::ofType(EquipmentType::SECONDARY_WEAPON),
                'help' => 'Secondary weapons the holder of this position may use.',
            ])
        ;
    }
}
