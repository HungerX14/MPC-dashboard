<?php

declare(strict_types=1);

namespace App\Connector;

use App\DTO\ArticleDTO;
use App\DTO\StatsDTO;
use App\Entity\Site;

/**
 * Interface for all site connectors
 * Each connector type (WordPress, Ghost, Strapi, Git, etc.) must implement this interface
 */
interface SiteConnectorInterface
{
    /**
     * Get the unique identifier for this connector type
     */
    public static function getType(): string;

    /**
     * Get the display name for this connector type
     */
    public static function getDisplayName(): string;

    /**
     * Get the description for this connector type
     */
    public static function getDescription(): string;

    /**
     * Get the icon name (for UI display)
     */
    public static function getIcon(): string;

    /**
     * Get the configuration fields required for this connector
     * @return array<string, array{label: string, type: string, required: bool, placeholder?: string, help?: string}>
     */
    public static function getConfigurationFields(): array;

    /**
     * Test the connection to the site
     */
    public function testConnection(Site $site): bool;

    /**
     * Publish an article to the site
     * @return array{success: bool, url?: string, id?: string|int, message?: string}
     */
    public function publishArticle(Site $site, ArticleDTO $article): array;

    /**
     * Fetch statistics from the site
     */
    public function fetchStats(Site $site): StatsDTO;

    /**
     * Fetch posts/articles from the site
     * @param array{page?: int, per_page?: int, status?: string, type?: string, search?: string} $filters
     * @return array{posts: array, total: int, pages: int}
     */
    public function fetchPosts(Site $site, array $filters = []): array;

    /**
     * Fetch a single post by ID
     * @return array|null
     */
    public function fetchPost(Site $site, string|int $postId): ?array;

    /**
     * Fetch pages from the site (if supported)
     * @param array{page?: int, per_page?: int, status?: string} $filters
     * @return array{pages: array, total: int, pages_count: int}
     */
    public function fetchPages(Site $site, array $filters = []): array;

    /**
     * Check if the connector supports a specific feature
     */
    public function supports(string $feature): bool;
}
