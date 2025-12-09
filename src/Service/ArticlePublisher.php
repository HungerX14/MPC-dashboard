<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\ArticleDTO;
use App\Entity\Site;
use App\Exception\WordpressApiException;
use Psr\Log\LoggerInterface;

/**
 * Service for publishing articles to WordPress sites
 */
class ArticlePublisher
{
    public function __construct(
        private readonly WordpressApiClient $apiClient,
        private readonly SiteManager $siteManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Publish an article to a specific site
     *
     * @return array{success: bool, message: string, data?: array<string, mixed>}
     */
    public function publishToSite(Site $site, ArticleDTO $article): array
    {
        $this->logger->info('Starting article publication', [
            'site_id' => $site->getId(),
            'site_name' => $site->getName(),
            'article_title' => $article->title,
        ]);

        try {
            $result = $this->apiClient->publishArticle($site, $article);

            $this->logger->info('Article published successfully', [
                'site_id' => $site->getId(),
                'post_id' => $result['post_id'] ?? null,
            ]);

            return [
                'success' => true,
                'message' => sprintf(
                    'Article "%s" publie avec succes sur %s',
                    $article->title,
                    $site->getName()
                ),
                'data' => $result,
            ];
        } catch (WordpressApiException $e) {
            $this->logger->error('Article publication failed', [
                'site_id' => $site->getId(),
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            return [
                'success' => false,
                'message' => $e->getUserMessage(),
            ];
        }
    }

    /**
     * Publish an article to multiple sites
     *
     * @param Site[] $sites
     * @return array<int, array{site: string, success: bool, message: string}>
     */
    public function publishToMultipleSites(array $sites, ArticleDTO $article): array
    {
        $results = [];

        foreach ($sites as $site) {
            $result = $this->publishToSite($site, $article);
            $results[$site->getId()] = [
                'site' => $site->getName(),
                'success' => $result['success'],
                'message' => $result['message'],
            ];
        }

        $successCount = count(array_filter($results, fn($r) => $r['success']));
        $this->logger->info('Batch publication completed', [
            'total' => count($sites),
            'success' => $successCount,
            'failed' => count($sites) - $successCount,
        ]);

        return $results;
    }

    /**
     * Validate article before publishing
     *
     * @return string[] Array of validation errors
     */
    public function validateArticle(ArticleDTO $article): array
    {
        $errors = [];

        if (empty(trim($article->title))) {
            $errors[] = 'Le titre de l\'article est requis.';
        }

        if (strlen($article->title) > 500) {
            $errors[] = 'Le titre ne peut pas depasser 500 caracteres.';
        }

        if (empty(trim($article->content))) {
            $errors[] = 'Le contenu de l\'article est requis.';
        }

        return $errors;
    }

    /**
     * Get available sites for publishing
     *
     * @return Site[]
     */
    public function getAvailableSites(): array
    {
        return $this->siteManager->getAllSites();
    }
}
