<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Model;

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Resource\Model\ResourceInterface;

/**
 * The tracked page view an order is credited to, copied when the order is completed
 */
interface AttributionInterface extends ResourceInterface
{
    public function getOrder(): ?OrderInterface;

    public function setOrder(OrderInterface $order): void;

    public function getSource(): ?string;

    public function setSource(string $source): void;

    /**
     * Whether no non-direct page view was tracked in the lookback window, so the order is credited to the
     * newest direct one
     */
    public function isDirect(): bool;

    public function getMedium(): ?string;

    public function setMedium(?string $medium): void;

    public function getCampaign(): ?string;

    public function setCampaign(?string $campaign): void;

    public function getReferrer(): ?string;

    public function setReferrer(?string $referrer): void;

    public function getLandingPage(): ?string;

    public function setLandingPage(?string $landingPage): void;

    /**
     * When the credited page view was tracked
     */
    public function getTrackedAt(): ?\DateTimeInterface;

    public function setTrackedAt(\DateTimeInterface $trackedAt): void;
}
