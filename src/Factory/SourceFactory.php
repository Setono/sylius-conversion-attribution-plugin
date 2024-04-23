<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Factory;

use Setono\SyliusConversionAttributionPlugin\ClientInformation\ClientInformation;
use Setono\SyliusConversionAttributionPlugin\Model\SourceInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Webmozart\Assert\Assert;

final class SourceFactory implements SourceFactoryInterface
{
    public function __construct(
        private readonly FactoryInterface $decorated,
    ) {
    }

    public function createNew(): SourceInterface
    {
        /** @var SourceInterface|object $obj */
        $obj = $this->decorated->createNew();
        Assert::isInstanceOf($obj, SourceInterface::class);

        return $obj;
    }

    public function createFromClientInformation(ClientInformation $clientInformation): SourceInterface
    {
        $obj = $this->createNew();

        $obj->setClientId($clientInformation->clientId);
        $obj->setIp($clientInformation->ip);
        $obj->setUserAgent($clientInformation->userAgent);
        $obj->setPage($clientInformation->page);
        $obj->setReferrer($clientInformation->referrer);
        $obj->setSource($clientInformation->source);
        $obj->setMedium($clientInformation->medium);
        $obj->setCampaign($clientInformation->campaign);

        return $obj;
    }
}
