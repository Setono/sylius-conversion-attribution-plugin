<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\ClientInformation;

use Symfony\Component\Validator\Constraints as Assert;

final class ClientInformation implements \JsonSerializable
{
    #[Assert\Length(max: 255)]
    public ?string $clientId = null;

    #[Assert\Length(max: 255)]
    public ?string $ip = null;

    #[Assert\Length(max: 1024)]
    public ?string $userAgent = null;

    #[Assert\Length(max: 4096)]
    public ?string $page = null;

    #[Assert\Length(max: 4096)]
    public ?string $referrer = null;

    #[Assert\Length(max: 255)]
    public ?string $source = null;

    #[Assert\Length(max: 255)]
    public ?string $medium = null;

    #[Assert\Length(max: 255)]
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
