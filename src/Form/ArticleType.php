<?php

declare(strict_types=1);

namespace App\Form;

use App\DTO\ArticleDTO;
use App\Entity\Site;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ArticleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $sites = $options['sites'];
        $selectedSite = $options['selected_site'];

        $builder
            ->add('site', EntityType::class, [
                'class' => Site::class,
                'choices' => $sites,
                'choice_label' => 'name',
                'label' => 'Site de publication',
                'mapped' => false,
                'data' => $selectedSite,
                'attr' => [
                    'class' => 'form-select',
                ],
            ])
            ->add('title', TextType::class, [
                'label' => 'Titre de l\'article',
                'attr' => [
                    'placeholder' => 'Entrez le titre de votre article',
                    'class' => 'form-input',
                ],
            ])
            ->add('content', TextareaType::class, [
                'label' => 'Contenu',
                'attr' => [
                    'placeholder' => 'Redigez votre article ici...',
                    'class' => 'form-textarea',
                    'rows' => 15,
                ],
            ])
            ->add('excerpt', TextareaType::class, [
                'label' => 'Extrait (optionnel)',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Un court resume de l\'article',
                    'class' => 'form-textarea',
                    'rows' => 3,
                ],
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'Brouillon' => 'draft',
                    'Publier immediatement' => 'publish',
                    'En attente de relecture' => 'pending',
                ],
                'attr' => [
                    'class' => 'form-select',
                ],
            ])
            ->add('categoriesInput', TextType::class, [
                'label' => 'Categories',
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'placeholder' => 'categorie1, categorie2, categorie3',
                    'class' => 'form-input',
                ],
                'help' => 'Separez les categories par des virgules',
            ])
            ->add('tagsInput', TextType::class, [
                'label' => 'Tags',
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'placeholder' => 'tag1, tag2, tag3',
                    'class' => 'form-input',
                ],
                'help' => 'Separez les tags par des virgules',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ArticleDTO::class,
            'sites' => [],
            'selected_site' => null,
        ]);

        $resolver->setAllowedTypes('sites', 'array');
        $resolver->setAllowedTypes('selected_site', ['null', Site::class]);
    }
}
