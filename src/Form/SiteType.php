<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Site;
use App\Service\ConnectorFactory;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SiteType extends AbstractType
{
    public function __construct(
        private readonly ConnectorFactory $connectorFactory,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $connectors = $this->connectorFactory->getAvailableConnectors();
        $connectorChoices = [];
        foreach ($connectors as $type => $info) {
            $connectorChoices[$info['name']] = $type;
        }

        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom du site',
                'attr' => [
                    'placeholder' => 'Mon site',
                    'class' => 'form-input',
                ],
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type de connexion',
                'choices' => $connectorChoices,
                'attr' => [
                    'class' => 'form-input',
                    'data-connector-selector' => 'true',
                ],
                'choice_attr' => function ($choice, $key, $value) use ($connectors) {
                    return [
                        'data-description' => $connectors[$value]['description'] ?? '',
                        'data-icon' => $connectors[$value]['icon'] ?? '',
                    ];
                },
            ])
            ->add('url', TextType::class, [
                'label' => 'URL',
                'attr' => [
                    'placeholder' => 'https://monsite.com',
                    'class' => 'form-input',
                ],
            ])
            ->add('apiToken', TextType::class, [
                'label' => 'Token / Cle API',
                'attr' => [
                    'placeholder' => 'Votre token d\'authentification',
                    'class' => 'form-input',
                ],
            ])
        ;

        // Add dynamic fields based on connector type
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($connectors) {
            $site = $event->getData();
            $form = $event->getForm();
            $type = $site?->getType() ?? 'wordpress';

            $this->addConnectorFields($form, $type, $connectors);
        });

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use ($connectors) {
            $data = $event->getData();
            $form = $event->getForm();
            $type = $data['type'] ?? 'wordpress';

            $this->addConnectorFields($form, $type, $connectors);
        });
    }

    private function addConnectorFields($form, string $type, array $connectors): void
    {
        $fields = $connectors[$type]['fields'] ?? [];

        foreach ($fields as $fieldName => $fieldConfig) {
            // Skip url and apiToken as they're already added
            if (in_array($fieldName, ['url', 'apiToken'])) {
                continue;
            }

            $fieldType = match ($fieldConfig['type'] ?? 'text') {
                'select' => ChoiceType::class,
                default => TextType::class,
            };

            $fieldOptions = [
                'label' => $fieldConfig['label'] ?? $fieldName,
                'required' => $fieldConfig['required'] ?? false,
                'mapped' => false,
                'attr' => [
                    'placeholder' => $fieldConfig['placeholder'] ?? '',
                    'class' => 'form-input config-field config-field-' . $type,
                    'data-connector-type' => $type,
                ],
            ];

            if (isset($fieldConfig['help'])) {
                $fieldOptions['help'] = $fieldConfig['help'];
            }

            if ($fieldType === ChoiceType::class && isset($fieldConfig['options'])) {
                $fieldOptions['choices'] = array_flip($fieldConfig['options']);
            }

            $form->add('config_' . $fieldName, $fieldType, $fieldOptions);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Site::class,
        ]);
    }
}
