<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SiteRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SiteRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Site
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le nom du site est requis')]
    private ?string $name = null;

    #[ORM\Column(length: 500)]
    #[Assert\NotBlank(message: "L'URL du site est requise")]
    #[Assert\Regex(
        pattern: '/^https?:\/\/.+/',
        message: "L'URL doit commencer par http:// ou https://"
    )]
    private ?string $url = null;

    #[ORM\Column(length: 500)]
    #[Assert\NotBlank(message: 'Le token API est requis')]
    private ?string $apiToken = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $updatedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastCheckedAt = null;

    #[ORM\Column(length: 20, options: ['default' => 'unknown'])]
    private string $status = 'unknown';

    #[ORM\Column(length: 50, options: ['default' => 'wordpress'])]
    private string $type = 'wordpress';

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $config = null;

    #[ORM\ManyToOne(targetEntity: \App\Entity\User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $owner = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(string $url): static
    {
        // Ensure URL doesn't have trailing slash
        $this->url = rtrim($url, '/');

        return $this;
    }

    public function getApiToken(): ?string
    {
        return $this->apiToken;
    }

    public function setApiToken(string $apiToken): static
    {
        $this->apiToken = $apiToken;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function getLastCheckedAt(): ?\DateTimeImmutable
    {
        return $this->lastCheckedAt;
    }

    public function setLastCheckedAt(?\DateTimeImmutable $lastCheckedAt): static
    {
        $this->lastCheckedAt = $lastCheckedAt;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    /**
     * Get the full API endpoint URL for a given path
     */
    public function getApiEndpoint(string $path): string
    {
        return sprintf('%s/wp-json/ma-plateforme/v1/%s', $this->url, ltrim($path, '/'));
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getConfig(): ?array
    {
        return $this->config;
    }

    public function setConfig(?array $config): static
    {
        $this->config = $config;

        return $this;
    }

    /**
     * Get a specific config value
     */
    public function getConfigValue(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * Set a specific config value
     */
    public function setConfigValue(string $key, mixed $value): static
    {
        if ($this->config === null) {
            $this->config = [];
        }
        $this->config[$key] = $value;

        return $this;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;

        return $this;
    }
}
