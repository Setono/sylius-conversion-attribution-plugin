<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\ClientInformation;

final class ClientInformation implements \JsonSerializable
{
    public ?string $clientId = null;

    public ?string $ip = null;

    public ?string $userAgent = null;

    public ?string $page = null;

    public ?string $referrer = null;

    public ?string $source = null;

    public ?string $medium = null;

    public ?string $campaign = null;

    public function jsonSerialize(): array
    {
        return array_filter([
            'clientId' => $this->clientId,
            'ip' => $this->ip,
            'userAgent' => $this->userAgent,
            'page' => $this->page,
            'referrer' => $this->referrer,
            'source' => $this->source,
            'medium' => $this->medium,
            'campaign' => $this->campaign,
        ], static function (mixed $value): bool {
            if (null === $value) {
                return false;
            }

            if ('' === $value) {
                return false;
            }

            return true;
        });
    }
}
