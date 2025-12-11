<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Site;
use App\Service\ConnectorFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/sites/{id}/content')]
class SiteContentController extends AbstractController
{
    public function __construct(
        private readonly ConnectorFactory $connectorFactory,
    ) {
    }

    #[Route('', name: 'app_site_content', methods: ['GET'])]
    public function index(Site $site, Request $request): Response
    {
        $connector = $this->connectorFactory->getConnector($site);
        $connectorInfo = $this->connectorFactory->getAvailableConnectors()[$site->getType()] ?? null;

        $page = max(1, $request->query->getInt('page', 1));
        $perPage = 10;
        $search = $request->query->getString('search', '');
        $type = $request->query->getString('type', 'posts'); // posts or pages

        $filters = [
            'page' => $page,
            'per_page' => $perPage,
            'search' => $search,
        ];

        if ($type === 'pages') {
            $result = $connector->fetchPages($site, $filters);
            $items = $result['pages'];
            $total = $result['total'];
            $totalPages = $result['pages_count'];
        } else {
            $result = $connector->fetchPosts($site, $filters);
            $items = $result['posts'];
            $total = $result['total'];
            $totalPages = $result['pages'];
        }

        return $this->render('site/content/index.html.twig', [
            'site' => $site,
            'connector' => $connectorInfo,
            'items' => $items,
            'type' => $type,
            'total' => $total,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'search' => $search,
        ]);
    }

    #[Route('/posts/{postId}', name: 'app_site_content_post', methods: ['GET'])]
    public function showPost(Site $site, string $postId): Response
    {
        $connector = $this->connectorFactory->getConnector($site);
        $connectorInfo = $this->connectorFactory->getAvailableConnectors()[$site->getType()] ?? null;

        $post = $connector->fetchPost($site, $postId);

        if (!$post) {
            throw $this->createNotFoundException('Article non trouve');
        }

        return $this->render('site/content/show.html.twig', [
            'site' => $site,
            'connector' => $connectorInfo,
            'post' => $post,
        ]);
    }
}
