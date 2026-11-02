<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\Doctrine\Fixture;

use Setono\SyliusConversionAttributionPlugin\Model\Source;

/**
 * Makes the plugin's Source mapped superclass a concrete entity, with a settable time of tracking
 */
class TrackedSource extends Source
{
    public static function at(\DateTimeInterface $createdAt, string $clientId = 'client', string $source = 'direct'): self
    {
        $trackedSource = new self();
        $trackedSource->createdAt = $createdAt;
        $trackedSource->setClientId($clientId);
        $trackedSource->setSource($source);

        return $trackedSource;
    }
}
