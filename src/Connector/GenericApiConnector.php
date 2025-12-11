<?php

declare(strict_types=1);

namespace App\Connector;

use App\DTO\ArticleDTO;
use App\DTO\StatsDTO;
use App\Entity\Site;

/**
 * Generic REST API connector for any CMS with a compatible API
 * Supports custom endpoint configuration
 */
class GenericApiConnector extends AbstractSiteConnector
{
    public static function getType(): string
    {
        return 'api';
    }

    public static function getDisplayName(): string
    {
        return 'API REST';
    }

    public static function getDescription(): string
    {
        return 'Connectez n\'importe quel CMS ou application via une API REST. Configurez vos endpoints personnalises.';
    }

    public static function getIcon(): string
    {
        return 'api';
    }

    public static function getConfigurationFields(): array
    {
        return [
            'url' => [
                'label' => 'URL de base de l\'API',
                'type' => 'url',
                'required' => true,
                'placeholder' => 'https://api.monsite.com/v1',
                'help' => 'L\'URL de base de votre API REST',
            ],
            'apiToken' => [
                'label' => 'Token d\'authentification',
                'type' => 'password',
                'required' => true,
                'placeholder' => 'Bearer token ou API key',
                'help' => 'Token d\'authentification pour l\'API',
            ],
            'authType' => [
                'label' => 'Type d\'authentification',
                'type' => 'select',
                'required' => true,
                'options' => [
                    'bearer' => 'Bearer Token',
                    'api_key' => 'API Key (header)',
                    'basic' => 'Basic Auth',
                ],
                'help' => 'Methode d\'authentification utilisee par l\'API',
            ],
            'publishEndpoint' => [
                'label' => 'Endpoint de publication',
                'type' => 'text',
                'required' => false,
                'placeholder' => '/posts',
                'help' => 'Chemin relatif pour publier du contenu (POST)',
            ],
            'postsEndpoint' => [
                'label' => 'Endpoint des articles',
                'type' => 'text',
                'required' => false,
                'placeholder' => '/posts',
                'help' => 'Chemin relatif pour lister les articles (GET)',
            ],
            'statsEndpoint' => [
                'label' => 'Endpoint de statistiques',
                'type' => 'text',
                'required' => false,
                'placeholder' => '/stats',
                'help' => 'Chemin relatif pour recuperer les statistiques (GET)',
            ],
        ];
    }

    protected function getSupportedFeatures(): array
    {
        return [
            self::FEATURE_PUBLISH,
            self::FEATURE_STATS,
        ];
    }

    public function testConnection(Site $site): bool
    {
        try {
            $config = $site->getConfig();
            $endpoint = $config['statsEndpoint'] ?? '/health';
            $url = rtrim($site->getUrl(), '/') . '/' . ltrim($endpoint, '/');

            $this->makeRequest('GET', $url, $this->getAuthHeaders($site));
            return true;
        } catch (\Exception $e) {
            $this->logger->warning('Generic API connection test failed', [
                'site' => $site->getName(),
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function publishArticle(Site $site, ArticleDTO $article): array
    {
        $config = $site->getConfig();
        $endpoint = $config['publishEndpoint'] ?? '/posts';
        $url = rtrim($site->getUrl(), '/') . '/' . ltrim($endpoint, '/');

        $this->logger->info('Publishing article via Generic API', [
            'site' => $site->getName(),
            'url' => $url,
            'title' => $article->title,
        ]);

        try {
            $response = $this->makeRequest('POST', $url, array_merge(
                $this->getAuthHeaders($site),
                ['json' => $this->formatArticlePayload($article, $config)]
            ));

            return [
                'success' => true,
                'id' => $response['id'] ?? $response['data']['id'] ?? null,
                'url' => $response['url'] ?? $response['data']['url'] ?? null,
                'message' => 'Contenu publie avec succes',
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to publish via Generic API', [
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
        $config = $site->getConfig();
        $endpoint = $config['statsEndpoint'] ?? '/stats';
        $url = rtrim($site->getUrl(), '/') . '/' . ltrim($endpoint, '/');

        try {
            $response = $this->makeRequest('GET', $url, $this->getAuthHeaders($site));

            return new StatsDTO(
                totalPosts: $response['posts'] ?? $response['total_posts'] ?? $response['count'] ?? 0,
                totalCategories: $response['categories'] ?? $response['total_categories'] ?? 0,
                totalTags: $response['tags'] ?? $response['total_tags'] ?? 0,
                siteTitle: $response['title'] ?? $response['site_title'] ?? $site->getName(),
                wordpressVersion: $response['version'] ?? null,
                fetchedAt: new \DateTimeImmutable()
            );
        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch stats via Generic API', [
                'site' => $site->getName(),
                'error' => $e->getMessage(),
            ]);

            return new StatsDTO(
                totalPosts: 0,
                totalCategories: 0,
                totalTags: 0,
                siteTitle: $site->getName(),
                fetchedAt: new \DateTimeImmutable()
            );
        }
    }

    /**
     * Get authentication headers based on site configuration
     * @return array<string, mixed>
     */
    private function getAuthHeaders(Site $site): array
    {
        $config = $site->getConfig();
        $authType = $config['authType'] ?? 'bearer';
        $token = $site->getApiToken();

        return match ($authType) {
            'bearer' => [
                'headers' => ['Authorization' => 'Bearer ' . $token],
            ],
            'api_key' => [
                'headers' => ['X-API-Key' => $token],
            ],
            'basic' => [
                'headers' => ['Authorization' => 'Basic ' . base64_encode($token)],
            ],
            default => [
                'headers' => ['Authorization' => 'Bearer ' . $token],
            ],
        };
    }

    /**
     * Format article payload according to site configuration
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function formatArticlePayload(ArticleDTO $article, array $config): array
    {
        // Default payload format - can be customized based on config
        return [
            'title' => $article->title,
            'content' => $article->content,
            'excerpt' => $article->excerpt,
            'status' => $article->status,
            'categories' => $article->categories,
            'tags' => $article->tags,
        ];
    }

    public function fetchPosts(Site $site, array $filters = []): array
    {
        $config = $site->getConfig() ?? [];
        $endpoint = $config['postsEndpoint'] ?? '/posts';
        $url = rtrim($site->getUrl(), '/') . '/' . ltrim($endpoint, '/');

        $page = $filters['page'] ?? 1;
        $perPage = $filters['per_page'] ?? 10;

        $queryParams = http_build_query([
            'page' => $page,
            'per_page' => $perPage,
        ]);

        try {
            $response = $this->makeRequest('GET', $url . '?' . $queryParams, $this->getAuthHeaders($site));

            // Try to extract posts from various response formats
            $posts = $response['data'] ?? $response['posts'] ?? $response['items'] ?? $response;
            if (!is_array($posts)) {
                $posts = [];
            }

            // Format posts to a standard structure
            $formattedPosts = array_map(fn($post) => [
                'id' => $post['id'] ?? $post['_id'] ?? uniqid(),
                'title' => $post['title'] ?? $post['name'] ?? '',
                'excerpt' => $post['excerpt'] ?? $post['description'] ?? $post['summary'] ?? '',
                'content' => $post['content'] ?? $post['body'] ?? '',
                'status' => $post['status'] ?? 'publish',
                'url' => $post['url'] ?? $post['link'] ?? '',
                'date' => $post['date'] ?? $post['created_at'] ?? $post['createdAt'] ?? null,
                'modified' => $post['modified'] ?? $post['updated_at'] ?? $post['updatedAt'] ?? null,
                'type' => 'post',
            ], $posts);

            return [
                'posts' => $formattedPosts,
                'total' => $response['total'] ?? $response['meta']['total'] ?? count($posts),
                'pages' => $response['pages'] ?? $response['meta']['pages'] ?? 1,
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch posts via Generic API', [
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
        $config = $site->getConfig() ?? [];
        $endpoint = $config['postsEndpoint'] ?? '/posts';
        $url = rtrim($site->getUrl(), '/') . '/' . ltrim($endpoint, '/') . '/' . $postId;

        try {
            $response = $this->makeRequest('GET', $url, $this->getAuthHeaders($site));

            $post = $response['data'] ?? $response;

            return [
                'id' => $post['id'] ?? $post['_id'] ?? $postId,
                'title' => $post['title'] ?? $post['name'] ?? '',
                'excerpt' => $post['excerpt'] ?? $post['description'] ?? $post['summary'] ?? '',
                'content' => $post['content'] ?? $post['body'] ?? '',
                'status' => $post['status'] ?? 'publish',
                'url' => $post['url'] ?? $post['link'] ?? '',
                'date' => $post['date'] ?? $post['created_at'] ?? $post['createdAt'] ?? null,
                'modified' => $post['modified'] ?? $post['updated_at'] ?? $post['updatedAt'] ?? null,
                'type' => 'post',
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch post via Generic API', [
                'site' => $site->getName(),
                'postId' => $postId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    public function fetchPages(Site $site, array $filters = []): array
    {
        // Generic API might not distinguish between posts and pages
        // Return empty by default
        return [
            'pages' => [],
            'total' => 0,
            'pages_count' => 0,
        ];
    }
}
