<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Model;

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Resource\Model\ResourceInterface;

interface AttributionInterface extends ResourceInterface
{
    public function getOrder(): ?OrderInterface;

    public function setOrder(OrderInterface $order): void;

    public function hasPageView(): bool;

    public function isDirect(): bool;

    public function getSource(): ?string;

    public function setSource(string $source): void;

    public function getMedium(): ?string;

    public function setMedium(?string $medium): void;

    public function getCampaign(): ?string;

    public function setCampaign(?string $campaign): void;

    public function getReferrer(): ?string;

    public function setReferrer(?string $referrer): void;

    public function getLandingPage(): ?string;

    public function setLandingPage(?string $landingPage): void;

    public function getTrackedAt(): ?\DateTimeInterface;

    public function setTrackedAt(\DateTimeInterface $trackedAt): void;
}
