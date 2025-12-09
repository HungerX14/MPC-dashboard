<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Site;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SiteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom du site',
                'attr' => [
                    'placeholder' => 'Mon site WordPress',
                    'class' => 'form-input',
                ],
            ])
            ->add('url', TextType::class, [
                'label' => 'URL du site',
                'attr' => [
                    'placeholder' => 'http://host.docker.internal:8888/monsite',
                    'class' => 'form-input',
                ],
                'help' => 'L\'URL de base de votre site WordPress. En local avec Docker, utilisez http://host.docker.internal:8888/monsite au lieu de localhost',
            ])
            ->add('apiToken', TextType::class, [
                'label' => 'Token API',
                'attr' => [
                    'placeholder' => 'Votre token API genere par le plugin',
                    'class' => 'form-input',
                ],
                'help' => 'Token d\'authentification fourni par le plugin MPC',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Site::class,
        ]);
    }
}
