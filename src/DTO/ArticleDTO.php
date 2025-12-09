<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Data Transfer Object for article publication
 */
class ArticleDTO
{
    public function __construct(
        #[Assert\NotBlank(message: 'Le titre est requis')]
        #[Assert\Length(max: 500, maxMessage: 'Le titre ne peut pas depasser {{ limit }} caracteres')]
        public string $title = '',

        #[Assert\NotBlank(message: 'Le contenu est requis')]
        public string $content = '',

        /** @var string[] */
        public array $categories = [],

        /** @var string[] */
        public array $tags = [],

        public string $status = 'draft',

        public ?string $excerpt = null,

        public ?string $featuredImage = null,
    ) {
    }

    /**
     * Convert DTO to array for API request
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'content' => $this->content,
            'categories' => $this->categories,
            'tags' => $this->tags,
            'status' => $this->status,
            'excerpt' => $this->excerpt,
            'featured_image' => $this->featuredImage,
        ];
    }

    /**
     * Create DTO from form data
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            title: $data['title'] ?? '',
            content: $data['content'] ?? '',
            categories: $data['categories'] ?? [],
            tags: $data['tags'] ?? [],
            status: $data['status'] ?? 'draft',
            excerpt: $data['excerpt'] ?? null,
            featuredImage: $data['featured_image'] ?? null,
        );
    }
}
