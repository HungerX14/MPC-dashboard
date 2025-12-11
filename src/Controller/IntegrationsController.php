<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ConnectorFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/integrations')]
class IntegrationsController extends AbstractController
{
    public function __construct(
        private readonly ConnectorFactory $connectorFactory,
    ) {
    }

    #[Route('', name: 'app_integrations', methods: ['GET'])]
    public function index(): Response
    {
        $connectors = $this->connectorFactory->getAvailableConnectors();

        // Group connectors by category
        $categories = [
            'cms' => [
                'name' => 'CMS & Blogs',
                'description' => 'Connectez vos sites WordPress, Ghost, et autres CMS',
                'connectors' => [],
            ],
            'api' => [
                'name' => 'API & Headless',
                'description' => 'Integrez avec des API REST ou des CMS headless',
                'connectors' => [],
            ],
            'static' => [
                'name' => 'Sites Statiques',
                'description' => 'Publiez sur Hugo, Jekyll, Gatsby via Git',
                'connectors' => [],
            ],
        ];

        foreach ($connectors as $type => $connector) {
            if ($type === 'wordpress') {
                $categories['cms']['connectors'][$type] = $connector;
            } elseif ($type === 'api') {
                $categories['api']['connectors'][$type] = $connector;
            } elseif ($type === 'git') {
                $categories['static']['connectors'][$type] = $connector;
            }
        }

        // Upcoming integrations (not yet implemented)
        $upcoming = [
            [
                'name' => 'Ghost',
                'description' => 'Publication sur Ghost CMS',
                'icon' => 'ghost',
                'status' => 'coming_soon',
            ],
            [
                'name' => 'Strapi',
                'description' => 'Headless CMS open source',
                'icon' => 'strapi',
                'status' => 'coming_soon',
            ],
            [
                'name' => 'Contentful',
                'description' => 'CMS headless cloud',
                'icon' => 'contentful',
                'status' => 'planned',
            ],
            [
                'name' => 'Notion',
                'description' => 'Publication depuis Notion',
                'icon' => 'notion',
                'status' => 'planned',
            ],
            [
                'name' => 'Shopify',
                'description' => 'Blog Shopify',
                'icon' => 'shopify',
                'status' => 'planned',
            ],
            [
                'name' => 'Medium',
                'description' => 'Publication sur Medium',
                'icon' => 'medium',
                'status' => 'planned',
            ],
        ];

        return $this->render('integrations/index.html.twig', [
            'categories' => $categories,
            'upcoming' => $upcoming,
        ]);
    }
}
