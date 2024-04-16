<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Model;

use Symfony\Component\Uid\Uuid;

class Source implements SourceInterface
{
    protected string $id;

    protected ?string $clientId = null;

    protected ?string $ip = null;

    protected ?string $userAgent = null;

    protected ?string $page = null;

    protected ?string $referrer = null;

    protected ?string $source = null;

    protected ?string $medium = null;

    protected ?string $campaign = null;

    protected \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id = (string) Uuid::v7();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getClientId(): ?string
    {
        return $this->clientId;
    }

    public function setClientId(?string $clientId): void
    {
        $this->clientId = $clientId;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function setIp(?string $ip): void
    {
        $this->ip = $ip;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): void
    {
        $this->userAgent = $userAgent;
    }

    public function getPage(): ?string
    {
        return $this->page;
    }

    public function setPage(?string $page): void
    {
        $this->page = $page;
    }

    public function getReferrer(): ?string
    {
        return $this->referrer;
    }

    public function setReferrer(?string $referrer): void
    {
        $this->referrer = $referrer;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(?string $source): void
    {
        if (null !== $source) {
            $source = strtolower($source);
        }

        $this->source = $source;
    }

    public function getMedium(): ?string
    {
        return $this->medium;
    }

    public function setMedium(?string $medium): void
    {
        if (null !== $medium) {
            $medium = strtolower($medium);
        }

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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function __toString(): string
    {
        return sprintf(
            '[%s] %s (%s)',
            $this->createdAt->format('Y-m-d H:i'),
            (string) $this->source,
            (string) $this->medium,
        );
    }
}
