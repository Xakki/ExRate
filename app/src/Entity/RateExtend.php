<?php

declare(strict_types=1);

namespace App\Entity;

use App\Contract\RateEntityInterface;
use App\Repository\RateExtendRepository;
use App\Util\BcMath;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RateExtendRepository::class)]
#[ORM\Table(name: 'rates_extend')]
#[ORM\UniqueConstraint(name: 'unique_rates_extend_idx', columns: ['provider_id', 'base_currency', 'date', 'currency'])]
class RateExtend implements RateEntityInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\Column(type: Types::DATE_IMMUTABLE)]
        private \DateTimeImmutable $date,

        #[ORM\Column(length: 12)]
        private string $currency,

        #[ORM\Column(length: 12)]
        private string $baseCurrency,

        #[ORM\Column(type: Types::DECIMAL, precision: 20, scale: 8)]
        private string $rateOpen,

        #[ORM\Column(type: Types::DECIMAL, precision: 20, scale: 8)]
        private string $rateLow,

        #[ORM\Column(type: Types::DECIMAL, precision: 20, scale: 8)]
        private string $rateHigh,

        #[ORM\Column(type: Types::DECIMAL, precision: 20, scale: 8)]
        private string $rate, // Close

        #[ORM\Column(type: Types::INTEGER, options: ['unsigned' => true, 'default' => 1])]
        private int $providerId = 1,
    ) {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDate(): \DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(\DateTimeImmutable $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    public function getBaseCurrency(): string
    {
        return $this->baseCurrency;
    }

    public function setBaseCurrency(string $baseCurrency): static
    {
        $this->baseCurrency = $baseCurrency;

        return $this;
    }

    public function getRateOpen(): string
    {
        return $this->rateOpen;
    }

    public function setRateOpen(string $rateOpen): static
    {
        $this->rateOpen = $rateOpen;

        return $this;
    }

    public function getRateLow(): string
    {
        return $this->rateLow;
    }

    public function setRateLow(string $rateLow): static
    {
        $this->rateLow = $rateLow;

        return $this;
    }

    public function getRateHigh(): string
    {
        return $this->rateHigh;
    }

    public function setRateHigh(string $rateHigh): static
    {
        $this->rateHigh = $rateHigh;

        return $this;
    }

    public function getRate(bool $invert = false): string
    {
        if ($invert && $this->rate && is_numeric($this->rate)) {
            return BcMath::div(1, $this->rate, 10);
        }

        return $this->rate;
    }

    public function setRate(string $rate): static
    {
        $this->rate = $rate;

        return $this;
    }

    public function getProviderId(): int
    {
        return $this->providerId;
    }

    public function setProviderId(int $providerId): static
    {
        $this->providerId = $providerId;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
