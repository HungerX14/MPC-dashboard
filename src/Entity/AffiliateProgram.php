<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AffiliateProgramRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AffiliateProgramRepository::class)]
#[ORM\HasLifecycleCallbacks]
class AffiliateProgram
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le nom du programme est requis')]
    private ?string $name = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Url(message: "L'URL doit être valide")]
    private ?string $website = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Url(message: "L'URL du dashboard doit être valide")]
    private ?string $dashboardUrl = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $network = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $commissionRate = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $commissionType = null; // percentage, fixed, cpa, cpl

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $currency = 'EUR';

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $cookieDuration = null; // in days

    #[ORM\Column(length: 50)]
    private string $status = 'active'; // active, paused, ended

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $category = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $credentials = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $owner = null;

    #[ORM\OneToMany(mappedBy: 'program', targetEntity: AffiliateLink::class, orphanRemoval: true)]
    private Collection $links;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->links = new ArrayCollection();
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getWebsite(): ?string
    {
        return $this->website;
    }

    public function setWebsite(?string $website): static
    {
        $this->website = $website;
        return $this;
    }

    public function getDashboardUrl(): ?string
    {
        return $this->dashboardUrl;
    }

    public function setDashboardUrl(?string $dashboardUrl): static
    {
        $this->dashboardUrl = $dashboardUrl;
        return $this;
    }

    public function getNetwork(): ?string
    {
        return $this->network;
    }

    public function setNetwork(?string $network): static
    {
        $this->network = $network;
        return $this;
    }

    public function getCommissionRate(): ?string
    {
        return $this->commissionRate;
    }

    public function setCommissionRate(?string $commissionRate): static
    {
        $this->commissionRate = $commissionRate;
        return $this;
    }

    public function getCommissionType(): ?string
    {
        return $this->commissionType;
    }

    public function setCommissionType(?string $commissionType): static
    {
        $this->commissionType = $commissionType;
        return $this;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function setCurrency(?string $currency): static
    {
        $this->currency = $currency;
        return $this;
    }

    public function getCookieDuration(): ?int
    {
        return $this->cookieDuration;
    }

    public function setCookieDuration(?int $cookieDuration): static
    {
        $this->cookieDuration = $cookieDuration;
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

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(?string $category): static
    {
        $this->category = $category;
        return $this;
    }

    public function getCredentials(): ?array
    {
        return $this->credentials;
    }

    public function setCredentials(?array $credentials): static
    {
        $this->credentials = $credentials;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
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

    /**
     * @return Collection<int, AffiliateLink>
     */
    public function getLinks(): Collection
    {
        return $this->links;
    }

    public function addLink(AffiliateLink $link): static
    {
        if (!$this->links->contains($link)) {
            $this->links->add($link);
            $link->setProgram($this);
        }
        return $this;
    }

    public function removeLink(AffiliateLink $link): static
    {
        if ($this->links->removeElement($link)) {
            if ($link->getProgram() === $this) {
                $link->setProgram(null);
            }
        }
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

    public function getFormattedCommission(): string
    {
        if (!$this->commissionRate) {
            return 'Non défini';
        }

        return match ($this->commissionType) {
            'percentage' => $this->commissionRate . '%',
            'fixed', 'cpa', 'cpl' => $this->commissionRate . ' ' . $this->currency,
            default => $this->commissionRate,
        };
    }
}
