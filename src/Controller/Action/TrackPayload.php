<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Controller\Action;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * The JSON body the tracking script POSTs to TrackAction. It only carries what the browser knows and
 * the server doesn't; everything per-visitor (client id, source, IP, user agent) is resolved server side
 */
final class TrackPayload
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 4096)]
    public string $page = '';

    #[Assert\Length(max: 4096)]
    public ?string $referrer = null;
}
