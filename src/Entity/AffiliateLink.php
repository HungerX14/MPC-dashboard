<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AffiliateLinkRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AffiliateLinkRepository::class)]
#[ORM\HasLifecycleCallbacks]
class AffiliateLink
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le nom du lien est requis')]
    private ?string $name = null;

    #[ORM\Column(length: 1000)]
    #[Assert\NotBlank(message: "L'URL d'affiliation est requise")]
    #[Assert\Url(message: "L'URL doit être valide")]
    private ?string $url = null;

    #[ORM\Column(length: 100, unique: true)]
    private ?string $shortCode = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Url(message: "L'URL de destination doit être valide")]
    private ?string $destinationUrl = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\ManyToOne(targetEntity: AffiliateProgram::class, inversedBy: 'links')]
    #[ORM\JoinColumn(nullable: true)]
    private ?AffiliateProgram $program = null;

    #[ORM\ManyToOne(targetEntity: Site::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Site $site = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $owner = null;

    #[ORM\OneToMany(mappedBy: 'link', targetEntity: ClickTracking::class, orphanRemoval: true)]
    private Collection $clicks;

    #[ORM\Column(length: 50)]
    private string $status = 'active'; // active, paused, expired

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $tags = null;

    #[ORM\Column(type: 'integer')]
    private int $totalClicks = 0;

    #[ORM\Column(type: 'integer')]
    private int $uniqueClicks = 0;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $totalEarnings = '0.00';

    #[ORM\Column(type: 'integer')]
    private int $conversions = 0;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $updatedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastClickAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->clicks = new ArrayCollection();
        $this->shortCode = $this->generateShortCode();
    }

    private function generateShortCode(): string
    {
        return bin2hex(random_bytes(4));
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
        $this->url = $url;
        return $this;
    }

    public function getShortCode(): ?string
    {
        return $this->shortCode;
    }

    public function setShortCode(string $shortCode): static
    {
        $this->shortCode = $shortCode;
        return $this;
    }

    public function regenerateShortCode(): static
    {
        $this->shortCode = $this->generateShortCode();
        return $this;
    }

    public function getDestinationUrl(): ?string
    {
        return $this->destinationUrl;
    }

    public function setDestinationUrl(?string $destinationUrl): static
    {
        $this->destinationUrl = $destinationUrl;
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

    public function getProgram(): ?AffiliateProgram
    {
        return $this->program;
    }

    public function setProgram(?AffiliateProgram $program): static
    {
        $this->program = $program;
        return $this;
    }

    public function getSite(): ?Site
    {
        return $this->site;
    }

    public function setSite(?Site $site): static
    {
        $this->site = $site;
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
     * @return Collection<int, ClickTracking>
     */
    public function getClicks(): Collection
    {
        return $this->clicks;
    }

    public function addClick(ClickTracking $click): static
    {
        if (!$this->clicks->contains($click)) {
            $this->clicks->add($click);
            $click->setLink($this);
        }
        return $this;
    }

    public function removeClick(ClickTracking $click): static
    {
        if ($this->clicks->removeElement($click)) {
            if ($click->getLink() === $this) {
                $click->setLink(null);
            }
        }
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

    public function getTags(): ?array
    {
        return $this->tags;
    }

    public function setTags(?array $tags): static
    {
        $this->tags = $tags;
        return $this;
    }

    public function getTotalClicks(): int
    {
        return $this->totalClicks;
    }

    public function setTotalClicks(int $totalClicks): static
    {
        $this->totalClicks = $totalClicks;
        return $this;
    }

    public function incrementTotalClicks(): static
    {
        $this->totalClicks++;
        return $this;
    }

    public function getUniqueClicks(): int
    {
        return $this->uniqueClicks;
    }

    public function setUniqueClicks(int $uniqueClicks): static
    {
        $this->uniqueClicks = $uniqueClicks;
        return $this;
    }

    public function incrementUniqueClicks(): static
    {
        $this->uniqueClicks++;
        return $this;
    }

    public function getTotalEarnings(): string
    {
        return $this->totalEarnings;
    }

    public function setTotalEarnings(string $totalEarnings): static
    {
        $this->totalEarnings = $totalEarnings;
        return $this;
    }

    public function addEarnings(string $amount): static
    {
        $this->totalEarnings = bcadd($this->totalEarnings, $amount, 2);
        return $this;
    }

    public function getConversions(): int
    {
        return $this->conversions;
    }

    public function setConversions(int $conversions): static
    {
        $this->conversions = $conversions;
        return $this;
    }

    public function incrementConversions(): static
    {
        $this->conversions++;
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

    public function getLastClickAt(): ?\DateTimeImmutable
    {
        return $this->lastClickAt;
    }

    public function setLastClickAt(?\DateTimeImmutable $lastClickAt): static
    {
        $this->lastClickAt = $lastClickAt;
        return $this;
    }

    public function getConversionRate(): float
    {
        if ($this->uniqueClicks === 0) {
            return 0.0;
        }
        return round(($this->conversions / $this->uniqueClicks) * 100, 2);
    }

    public function getEarningsPerClick(): string
    {
        if ($this->totalClicks === 0) {
            return '0.00';
        }
        return bcdiv($this->totalEarnings, (string)$this->totalClicks, 2);
    }
}
