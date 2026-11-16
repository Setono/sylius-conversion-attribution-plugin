<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Model;

use Sylius\Component\Resource\Model\ResourceInterface;

interface SourceInterface extends ResourceInterface, \Stringable
{
    /**
     * The source of a page view that came with no click id, no UTM parameter and no referrer from another site
     */
    public const DIRECT = 'direct';

    /**
     * The source of a page view whose UTM parameters give a medium or campaign, but that nothing names a source
     * for. Unlike direct, it's a non-direct page view
     */
    public const NOT_SET = '(not set)';

    public function getClientId(): ?string;

    public function setClientId(?string $clientId): void;

    public function getIp(): ?string;

    public function setIp(?string $ip): void;

    public function getUserAgent(): ?string;

    public function setUserAgent(?string $userAgent): void;

    public function getPage(): ?string;

    public function setPage(?string $page): void;

    public function getReferrer(): ?string;

    public function setReferrer(?string $referrer): void;

    public function getSource(): ?string;

    public function setSource(?string $source): void;

    public function getMedium(): ?string;

    public function setMedium(?string $medium): void;

    public function getCampaign(): ?string;

    public function setCampaign(?string $campaign): void;

    public function getCreatedAt(): \DateTimeInterface;
}
