<?php

declare(strict_types=1);

namespace App\Connector;

use App\DTO\ArticleDTO;
use App\DTO\StatsDTO;
use App\Entity\Site;

/**
 * Connector for WordPress sites using the custom MPC plugin
 */
class WordPressConnector extends AbstractSiteConnector
{
    public static function getType(): string
    {
        return 'wordpress';
    }

    public static function getDisplayName(): string
    {
        return 'WordPress';
    }

    public static function getDescription(): string
    {
        return 'Connectez vos sites WordPress via le plugin WPilot. Publiez des articles, gerez les categories et suivez les statistiques.';
    }

    public static function getIcon(): string
    {
        return 'wordpress';
    }

    public static function getConfigurationFields(): array
    {
        return [
            'url' => [
                'label' => 'URL du site',
                'type' => 'url',
                'required' => true,
                'placeholder' => 'https://monsite.com',
                'help' => 'L\'URL de votre site WordPress (sans slash final)',
            ],
            'apiToken' => [
                'label' => 'Token API',
                'type' => 'password',
                'required' => true,
                'placeholder' => 'Votre token API',
                'help' => 'Token genere par le plugin WPilot sur votre site WordPress',
            ],
        ];
    }

    protected function getSupportedFeatures(): array
    {
        return [
            self::FEATURE_PUBLISH,
            self::FEATURE_STATS,
            self::FEATURE_CATEGORIES,
            self::FEATURE_TAGS,
            self::FEATURE_MEDIA,
            self::FEATURE_SCHEDULE,
            self::FEATURE_DRAFT,
        ];
    }

    public function testConnection(Site $site): bool
    {
        try {
            $this->fetchStats($site);
            return true;
        } catch (\Exception $e) {
            $this->logger->warning('WordPress connection test failed', [
                'site' => $site->getName(),
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function publishArticle(Site $site, ArticleDTO $article): array
    {
        $endpoint = $this->getApiEndpoint($site, 'publish');

        $this->logger->info('Publishing article to WordPress', [
            'site' => $site->getName(),
            'title' => $article->title,
        ]);

        try {
            $response = $this->makeRequest('POST', $endpoint, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $site->getApiToken(),
                ],
                'json' => $article->toArray(),
            ]);

            return [
                'success' => true,
                'id' => $response['post_id'] ?? null,
                'url' => $response['post_url'] ?? null,
                'message' => 'Article publie avec succes',
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to publish to WordPress', [
                'site' => $site->getName(),
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function fetchStats(Site $site): StatsDTO
    {
        $endpoint = $this->getApiEndpoint($site, 'stats');

        $response = $this->makeRequest('GET', $endpoint, [
            'headers' => [
                'Authorization' => 'Bearer ' . $site->getApiToken(),
            ],
        ]);

        return StatsDTO::fromApiResponse($response);
    }

    /**
     * Fetch categories from WordPress site
     * @return array<array{id: int, name: string, slug: string}>
     */
    public function fetchCategories(Site $site): array
    {
        $endpoint = $this->getApiEndpoint($site, 'categories');

        try {
            return $this->makeRequest('GET', $endpoint, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $site->getApiToken(),
                ],
            ]);
        } catch (\Exception $e) {
            $this->logger->warning('Failed to fetch categories', [
                'site' => $site->getName(),
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Fetch tags from WordPress site
     * @return array<array{id: int, name: string, slug: string}>
     */
    public function fetchTags(Site $site): array
    {
        $endpoint = $this->getApiEndpoint($site, 'tags');

        try {
            return $this->makeRequest('GET', $endpoint, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $site->getApiToken(),
                ],
            ]);
        } catch (\Exception $e) {
            $this->logger->warning('Failed to fetch tags', [
                'site' => $site->getName(),
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    public function fetchPosts(Site $site, array $filters = []): array
    {
        $page = $filters['page'] ?? 1;
        $perPage = $filters['per_page'] ?? 10;
        $status = $filters['status'] ?? 'any';
        $search = $filters['search'] ?? '';

        $queryParams = http_build_query([
            'page' => $page,
            'per_page' => $perPage,
            'status' => $status,
            'search' => $search,
        ]);

        $endpoint = $this->getApiEndpoint($site, 'posts') . '?' . $queryParams;

        try {
            $response = $this->makeRequest('GET', $endpoint, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $site->getApiToken(),
                ],
            ]);

            return [
                'posts' => $response['posts'] ?? [],
                'total' => $response['total'] ?? 0,
                'pages' => $response['pages'] ?? 0,
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch posts from WordPress', [
                'site' => $site->getName(),
                'error' => $e->getMessage(),
            ]);

            return [
                'posts' => [],
                'total' => 0,
                'pages' => 0,
            ];
        }
    }

    public function fetchPost(Site $site, string|int $postId): ?array
    {
        $endpoint = $this->getApiEndpoint($site, 'posts/' . $postId);

        try {
            return $this->makeRequest('GET', $endpoint, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $site->getApiToken(),
                ],
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch post from WordPress', [
                'site' => $site->getName(),
                'postId' => $postId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    public function fetchPages(Site $site, array $filters = []): array
    {
        $page = $filters['page'] ?? 1;
        $perPage = $filters['per_page'] ?? 10;
        $status = $filters['status'] ?? 'any';
        $search = $filters['search'] ?? '';

        $queryParams = http_build_query([
            'page' => $page,
            'per_page' => $perPage,
            'status' => $status,
            'search' => $search,
        ]);

        $endpoint = $this->getApiEndpoint($site, 'pages') . '?' . $queryParams;

        try {
            $response = $this->makeRequest('GET', $endpoint, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $site->getApiToken(),
                ],
            ]);

            return [
                'pages' => $response['pages'] ?? [],
                'total' => $response['total'] ?? 0,
                'pages_count' => $response['pages_count'] ?? 0,
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch pages from WordPress', [
                'site' => $site->getName(),
                'error' => $e->getMessage(),
            ]);

            return [
                'pages' => [],
                'total' => 0,
                'pages_count' => 0,
            ];
        }
    }

    private function getApiEndpoint(Site $site, string $path): string
    {
        return sprintf('%s/wp-json/ma-plateforme/v1/%s', $site->getUrl(), ltrim($path, '/'));
    }
}
