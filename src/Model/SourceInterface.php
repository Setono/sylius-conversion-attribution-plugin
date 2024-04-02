<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Model;

use Sylius\Component\Resource\Model\ResourceInterface;

interface SourceInterface extends ResourceInterface, \Stringable
{
    public function getClientId(): ?string;

    public function setClientId(?string $clientId): void;

    public function getReferrer(): ?string;

    public function setReferrer(?string $referrer): void;

    public function getSource(): ?string;

    public function setSource(?string $source): void;

    public function getMedium(): ?string;

    public function setMedium(?string $medium): void;

    public function getCampaign(): ?string;

    public function setCampaign(?string $campaign): void;

    public function getCreatedAt(): \DateTimeImmutable;
}
