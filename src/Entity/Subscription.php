<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SubscriptionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SubscriptionRepository::class)]
class Subscription
{
    public const PLAN_FREE = 'free';
    public const PLAN_STARTER = 'starter';
    public const PLAN_PRO = 'pro';
    public const PLAN_ENTERPRISE = 'enterprise';

    public const PLANS = [
        self::PLAN_FREE => [
            'name' => 'Free',
            'price' => 0,
            'sites_limit' => 1,
            'publications_per_month' => 10,
            'features' => ['1 site', '10 publications/mois', 'Support communautaire'],
        ],
        self::PLAN_STARTER => [
            'name' => 'Starter',
            'price' => 9,
            'sites_limit' => 5,
            'publications_per_month' => 100,
            'features' => ['5 sites', '100 publications/mois', 'Support email', 'Statistiques basiques'],
        ],
        self::PLAN_PRO => [
            'name' => 'Pro',
            'price' => 29,
            'sites_limit' => 25,
            'publications_per_month' => 1000,
            'features' => ['25 sites', '1000 publications/mois', 'Support prioritaire', 'Statistiques avancées', 'API access', 'Planification'],
        ],
        self::PLAN_ENTERPRISE => [
            'name' => 'Enterprise',
            'price' => 99,
            'sites_limit' => -1, // Unlimited
            'publications_per_month' => -1, // Unlimited
            'features' => ['Sites illimités', 'Publications illimitées', 'Support dédié', 'Toutes les fonctionnalités', 'Multi-utilisateurs', 'SLA 99.9%'],
        ],
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 20)]
    private string $plan = self::PLAN_FREE;

    #[ORM\Column]
    private int $sitesUsed = 0;

    #[ORM\Column]
    private int $publicationsThisMonth = 0;

    #[ORM\Column]
    private \DateTimeImmutable $currentPeriodStart;

    #[ORM\Column]
    private \DateTimeImmutable $currentPeriodEnd;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $stripeCustomerId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $stripeSubscriptionId = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->currentPeriodStart = new \DateTimeImmutable('first day of this month');
        $this->currentPeriodEnd = new \DateTimeImmutable('last day of this month 23:59:59');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getPlan(): string
    {
        return $this->plan;
    }

    public function setPlan(string $plan): static
    {
        $this->plan = $plan;
        return $this;
    }

    public function getPlanDetails(): array
    {
        return self::PLANS[$this->plan] ?? self::PLANS[self::PLAN_FREE];
    }

    public function getSitesUsed(): int
    {
        return $this->sitesUsed;
    }

    public function setSitesUsed(int $sitesUsed): static
    {
        $this->sitesUsed = $sitesUsed;
        return $this;
    }

    public function getPublicationsThisMonth(): int
    {
        return $this->publicationsThisMonth;
    }

    public function setPublicationsThisMonth(int $publicationsThisMonth): static
    {
        $this->publicationsThisMonth = $publicationsThisMonth;
        return $this;
    }

    public function incrementPublications(): static
    {
        $this->publicationsThisMonth++;
        return $this;
    }

    public function getCurrentPeriodStart(): \DateTimeImmutable
    {
        return $this->currentPeriodStart;
    }

    public function setCurrentPeriodStart(\DateTimeImmutable $currentPeriodStart): static
    {
        $this->currentPeriodStart = $currentPeriodStart;
        return $this;
    }

    public function getCurrentPeriodEnd(): \DateTimeImmutable
    {
        return $this->currentPeriodEnd;
    }

    public function setCurrentPeriodEnd(\DateTimeImmutable $currentPeriodEnd): static
    {
        $this->currentPeriodEnd = $currentPeriodEnd;
        return $this;
    }

    public function getStripeCustomerId(): ?string
    {
        return $this->stripeCustomerId;
    }

    public function setStripeCustomerId(?string $stripeCustomerId): static
    {
        $this->stripeCustomerId = $stripeCustomerId;
        return $this;
    }

    public function getStripeSubscriptionId(): ?string
    {
        return $this->stripeSubscriptionId;
    }

    public function setStripeSubscriptionId(?string $stripeSubscriptionId): static
    {
        $this->stripeSubscriptionId = $stripeSubscriptionId;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getSitesLimit(): int
    {
        return $this->getPlanDetails()['sites_limit'];
    }

    public function getPublicationsLimit(): int
    {
        return $this->getPlanDetails()['publications_per_month'];
    }

    public function canAddSite(): bool
    {
        $limit = $this->getSitesLimit();
        return $limit === -1 || $this->sitesUsed < $limit;
    }

    public function canPublish(): bool
    {
        $limit = $this->getPublicationsLimit();
        return $limit === -1 || $this->publicationsThisMonth < $limit;
    }

    public function getSitesUsagePercentage(): int
    {
        $limit = $this->getSitesLimit();
        if ($limit === -1) {
            return 0;
        }
        return (int) round(($this->sitesUsed / $limit) * 100);
    }

    public function getPublicationsUsagePercentage(): int
    {
        $limit = $this->getPublicationsLimit();
        if ($limit === -1) {
            return 0;
        }
        return (int) round(($this->publicationsThisMonth / $limit) * 100);
    }

    public function isPaid(): bool
    {
        return $this->plan !== self::PLAN_FREE;
    }
}
