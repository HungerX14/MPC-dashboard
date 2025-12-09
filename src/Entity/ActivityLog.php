<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ActivityLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ActivityLogRepository::class)]
#[ORM\Index(columns: ['created_at'], name: 'idx_activity_log_created_at')]
#[ORM\Index(columns: ['action'], name: 'idx_activity_log_action')]
class ActivityLog
{
    public const ACTION_SITE_CREATED = 'site.created';
    public const ACTION_SITE_UPDATED = 'site.updated';
    public const ACTION_SITE_DELETED = 'site.deleted';
    public const ACTION_SITE_ONLINE = 'site.online';
    public const ACTION_SITE_OFFLINE = 'site.offline';
    public const ACTION_ARTICLE_PUBLISHED = 'article.published';
    public const ACTION_ARTICLE_FAILED = 'article.failed';
    public const ACTION_ARTICLE_SCHEDULED = 'article.scheduled';
    public const ACTION_USER_LOGIN = 'user.login';
    public const ACTION_USER_LOGOUT = 'user.logout';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\Column(length: 50)]
    private string $action;

    #[ORM\Column(length: 255)]
    private string $description;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $metadata = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function setAction(string $action): static
    {
        $this->action = $action;
        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): static
    {
        $this->metadata = $metadata;
        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): static
    {
        $this->ipAddress = $ipAddress;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getIcon(): string
    {
        return match ($this->action) {
            self::ACTION_SITE_CREATED => 'plus-circle',
            self::ACTION_SITE_UPDATED => 'pencil',
            self::ACTION_SITE_DELETED => 'trash',
            self::ACTION_SITE_ONLINE => 'check-circle',
            self::ACTION_SITE_OFFLINE => 'x-circle',
            self::ACTION_ARTICLE_PUBLISHED => 'document-text',
            self::ACTION_ARTICLE_FAILED => 'exclamation-circle',
            self::ACTION_ARTICLE_SCHEDULED => 'clock',
            self::ACTION_USER_LOGIN => 'login',
            self::ACTION_USER_LOGOUT => 'logout',
            default => 'information-circle',
        };
    }

    public function getColor(): string
    {
        return match ($this->action) {
            self::ACTION_SITE_CREATED, self::ACTION_SITE_ONLINE, self::ACTION_ARTICLE_PUBLISHED => 'green',
            self::ACTION_SITE_DELETED, self::ACTION_SITE_OFFLINE, self::ACTION_ARTICLE_FAILED => 'red',
            self::ACTION_SITE_UPDATED => 'blue',
            self::ACTION_ARTICLE_SCHEDULED => 'yellow',
            self::ACTION_USER_LOGIN, self::ACTION_USER_LOGOUT => 'gray',
            default => 'gray',
        };
    }
}
