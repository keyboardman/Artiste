<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\UX\Dropzone\Form\DropzoneType;

class ArticleUploadType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('image', DropzoneType::class, [
                'label' => false,
                'attr'  => [
                    'placeholder' => 'Glissez votre image ici ou cliquez pour choisir',
                    'accept'      => 'image/jpeg,image/png,image/gif,image/webp',
                ],
                'constraints' => [
                    new File(
                        maxSize: '10M',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
                        mimeTypesMessage: 'Format non supporté. Utilisez JPG, PNG, GIF ou WebP.',
                        maxSizeMessage: 'L\'image ne doit pas dépasser 10 Mo.',
                    ),
                ],
            ])
            ->add('title', TextType::class, [
                'label'       => false,
                'attr'        => ['placeholder' => 'Titre de l\'œuvre'],
                'constraints' => [new NotBlank(message: 'Le titre est requis.')],
            ])
            ->add('description', TextType::class, [
                'label'    => false,
                'required' => false,
                'attr'     => ['placeholder' => 'Description (optionnel)'],
            ])
            ->add('category', ChoiceType::class, [
                'label'       => false,
                'required'    => false,
                'placeholder' => 'Catégorie',
                'choices'     => [
                    'Illustration'     => 'illustration',
                    'Photographie'     => 'photographie',
                    'Graphisme'        => 'graphisme',
                    'Peinture'         => 'peinture',
                    'Digital Painting' => 'digital-painting',
                    'Motion Design'    => 'motion-design',
                ],
            ])
            ->add('price', TextType::class, [
                'label'    => false,
                'required' => false,
                'attr'     => [
                    'placeholder' => 'Prix en € (optionnel)',
                    'pattern'     => '^\d+([.,]\d{1,2})?$',
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
