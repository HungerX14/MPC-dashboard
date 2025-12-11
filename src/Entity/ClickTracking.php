<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ClickTrackingRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClickTrackingRepository::class)]
#[ORM\Index(columns: ['clicked_at'])]
#[ORM\Index(columns: ['ip_hash'])]
class ClickTracking
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: AffiliateLink::class, inversedBy: 'clicks')]
    #[ORM\JoinColumn(nullable: false)]
    private ?AffiliateLink $link = null;

    #[ORM\Column(length: 64)]
    private ?string $ipHash = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(length: 1000, nullable: true)]
    private ?string $referer = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $country = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $city = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $device = null; // desktop, mobile, tablet

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $browser = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $os = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $utmParams = null;

    #[ORM\Column]
    private bool $isUnique = false;

    #[ORM\Column]
    private bool $isConversion = false;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $conversionValue = null;

    #[ORM\Column]
    private \DateTimeImmutable $clickedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $convertedAt = null;

    public function __construct()
    {
        $this->clickedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLink(): ?AffiliateLink
    {
        return $this->link;
    }

    public function setLink(?AffiliateLink $link): static
    {
        $this->link = $link;
        return $this;
    }

    public function getIpHash(): ?string
    {
        return $this->ipHash;
    }

    public function setIpHash(string $ipHash): static
    {
        $this->ipHash = $ipHash;
        return $this;
    }

    public static function hashIp(string $ip): string
    {
        return hash('sha256', $ip . $_ENV['APP_SECRET'] ?? 'default_salt');
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): static
    {
        $this->userAgent = $userAgent ? substr($userAgent, 0, 500) : null;
        return $this;
    }

    public function getReferer(): ?string
    {
        return $this->referer;
    }

    public function setReferer(?string $referer): static
    {
        $this->referer = $referer ? substr($referer, 0, 1000) : null;
        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(?string $country): static
    {
        $this->country = $country;
        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): static
    {
        $this->city = $city;
        return $this;
    }

    public function getDevice(): ?string
    {
        return $this->device;
    }

    public function setDevice(?string $device): static
    {
        $this->device = $device;
        return $this;
    }

    public function getBrowser(): ?string
    {
        return $this->browser;
    }

    public function setBrowser(?string $browser): static
    {
        $this->browser = $browser;
        return $this;
    }

    public function getOs(): ?string
    {
        return $this->os;
    }

    public function setOs(?string $os): static
    {
        $this->os = $os;
        return $this;
    }

    public function getUtmParams(): ?array
    {
        return $this->utmParams;
    }

    public function setUtmParams(?array $utmParams): static
    {
        $this->utmParams = $utmParams;
        return $this;
    }

    public function isUnique(): bool
    {
        return $this->isUnique;
    }

    public function setIsUnique(bool $isUnique): static
    {
        $this->isUnique = $isUnique;
        return $this;
    }

    public function isConversion(): bool
    {
        return $this->isConversion;
    }

    public function setIsConversion(bool $isConversion): static
    {
        $this->isConversion = $isConversion;
        return $this;
    }

    public function getConversionValue(): ?string
    {
        return $this->conversionValue;
    }

    public function setConversionValue(?string $conversionValue): static
    {
        $this->conversionValue = $conversionValue;
        return $this;
    }

    public function getClickedAt(): \DateTimeImmutable
    {
        return $this->clickedAt;
    }

    public function getConvertedAt(): ?\DateTimeImmutable
    {
        return $this->convertedAt;
    }

    public function setConvertedAt(?\DateTimeImmutable $convertedAt): static
    {
        $this->convertedAt = $convertedAt;
        return $this;
    }

    public function markAsConversion(?string $value = null): static
    {
        $this->isConversion = true;
        $this->convertedAt = new \DateTimeImmutable();
        $this->conversionValue = $value;
        return $this;
    }
}
