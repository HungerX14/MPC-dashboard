<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Data Transfer Object for WordPress site statistics
 */
class StatsDTO
{
    public function __construct(
        public int $totalPosts = 0,
        public int $totalCategories = 0,
        public int $totalTags = 0,
        public int $totalPages = 0,
        public int $totalComments = 0,
        public int $totalUsers = 0,
        public ?string $siteTitle = null,
        public ?string $siteDescription = null,
        public ?string $wordpressVersion = null,
        public ?\DateTimeImmutable $fetchedAt = null,
    ) {
        $this->fetchedAt = $this->fetchedAt ?? new \DateTimeImmutable();
    }

    /**
     * Create DTO from API response
     *
     * @param array<string, mixed> $data
     */
    public static function fromApiResponse(array $data): self
    {
        return new self(
            totalPosts: (int) ($data['total_posts'] ?? 0),
            totalCategories: (int) ($data['total_categories'] ?? 0),
            totalTags: (int) ($data['total_tags'] ?? 0),
            totalPages: (int) ($data['total_pages'] ?? 0),
            totalComments: (int) ($data['total_comments'] ?? 0),
            totalUsers: (int) ($data['total_users'] ?? 0),
            siteTitle: $data['site_title'] ?? null,
            siteDescription: $data['site_description'] ?? null,
            wordpressVersion: $data['wordpress_version'] ?? null,
        );
    }

    /**
     * Convert to array for display
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'total_posts' => $this->totalPosts,
            'total_categories' => $this->totalCategories,
            'total_tags' => $this->totalTags,
            'total_pages' => $this->totalPages,
            'total_comments' => $this->totalComments,
            'total_users' => $this->totalUsers,
            'site_title' => $this->siteTitle,
            'site_description' => $this->siteDescription,
            'wordpress_version' => $this->wordpressVersion,
            'fetched_at' => $this->fetchedAt?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Check if stats are empty (likely an error in fetching)
     */
    public function isEmpty(): bool
    {
        return $this->totalPosts === 0
            && $this->totalCategories === 0
            && $this->totalTags === 0;
    }
}
