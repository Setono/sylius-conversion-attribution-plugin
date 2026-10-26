<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\ClientInformation;

use Symfony\Component\Validator\Constraints as Assert;

final class ClientInformation
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
}
