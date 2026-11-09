<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Model;

use Sylius\Component\Core\Model\OrderInterface;

class Attribution implements AttributionInterface
{
    protected ?int $id = null;

    protected ?OrderInterface $order = null;

    protected ?string $source = null;

    protected ?string $medium = null;

    protected ?string $campaign = null;

    protected ?string $referrer = null;

    protected ?string $landingPage = null;

    protected ?\DateTimeInterface $trackedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrder(): ?OrderInterface
    {
        return $this->order;
    }

    public function setOrder(OrderInterface $order): void
    {
        $this->order = $order;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(string $source): void
    {
        $this->source = $source;
    }

    public function hasPageView(): bool
    {
        return null !== $this->trackedAt;
    }

    public function isDirect(): bool
    {
        return SourceInterface::DIRECT === $this->source;
    }

    public function getMedium(): ?string
    {
        return $this->medium;
    }

    public function setMedium(?string $medium): void
    {
        $this->medium = $medium;
    }

    public function getCampaign(): ?string
    {
        return $this->campaign;
    }

    public function setCampaign(?string $campaign): void
    {
        $this->campaign = $campaign;
    }

    public function getReferrer(): ?string
    {
        return $this->referrer;
    }

    public function setReferrer(?string $referrer): void
    {
        $this->referrer = $referrer;
    }

    public function getLandingPage(): ?string
    {
        return $this->landingPage;
    }

    public function setLandingPage(?string $landingPage): void
    {
        $this->landingPage = $landingPage;
    }

    public function getTrackedAt(): ?\DateTimeInterface
    {
        return $this->trackedAt;
    }

    public function setTrackedAt(\DateTimeInterface $trackedAt): void
    {
        $this->trackedAt = $trackedAt;
    }
}
