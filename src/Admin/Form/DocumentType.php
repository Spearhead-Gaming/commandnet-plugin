<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Admin\Form;

use Forumify\Core\Form\RichTextEditorType;
use MajesticDev\CommandNet\Entity\Document;
use MajesticDev\CommandNet\Service\DocumentRenderer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<Document>
 */
class DocumentType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Document::class,
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $placeholders = implode(', ', array_map(
            static fn (string $name): string => '{' . $name . '}',
            array_keys(DocumentRenderer::PLACEHOLDERS),
        ));

        $builder
            ->add('name', TextType::class)
            ->add('description', TextareaType::class, [
                'required' => false,
                'empty_data' => '',
                'help' => 'Only shown to staff, to tell documents apart.',
            ])
            ->add('content', RichTextEditorType::class, [
                'help' => 'Placeholders are replaced with the soldier and record values when the document is shown: ' . $placeholders,
            ])
        ;
    }
}
