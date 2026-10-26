<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\Provider\Fixture;

use Setono\SyliusConversionAttributionPlugin\Model\Source;

/**
 * Makes the plugin's mapped superclass a concrete entity (as Sylius' resource bundle does in an app),
 * with a settable creation time
 */
class TrackedSource extends Source
{
    public function __construct(string $clientId, string $source, \DateTimeInterface $createdAt)
    {
        parent::__construct();

        $this->clientId = $clientId;
        $this->source = $source;
        $this->createdAt = $createdAt;
    }
}
