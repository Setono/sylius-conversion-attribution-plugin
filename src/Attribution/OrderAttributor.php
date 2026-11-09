<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Attribution;

use Setono\SyliusConversionAttributionPlugin\Model\AttributionInterface;
use Setono\SyliusConversionAttributionPlugin\Model\CustomerInterface;
use Setono\SyliusConversionAttributionPlugin\Model\OrderInterface;
use Setono\SyliusConversionAttributionPlugin\Model\SourceInterface;
use Setono\SyliusConversionAttributionPlugin\Repository\SourceRepositoryInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;

final class OrderAttributor implements OrderAttributorInterface
{
    public function __construct(
        private readonly SourceRepositoryInterface $sourceRepository,
        /** @var FactoryInterface<covariant AttributionInterface> $attributionFactory */
        private readonly FactoryInterface $attributionFactory,
        // In days
        private readonly int $lookbackWindow,
    ) {
    }

    public function attribute(OrderInterface $order): AttributionInterface
    {
        $clientId = $order->getClientId();
        $completedAt = $order->getCheckoutCompletedAt();
        if (null === $clientId || null === $completedAt) {
            throw new \InvalidArgumentException('Only completed orders with a client id can be attributed');
        }

        $attribution = $this->attributionFactory->createNew();
        $attribution->setOrder($order);

        $clientIds = [$clientId];
        $customer = $order->getCustomer();
        if ($customer instanceof CustomerInterface) {
            $clientIds = [$clientId, ...$customer->getClientIds()];
        }

        $windowStart = \DateTimeImmutable::createFromInterface($completedAt)->modify(sprintf('-%d days', $this->lookbackWindow));
        $pageView = $this->sourceRepository->findPageViewToCredit($clientIds, $windowStart, $completedAt);
        if (null === $pageView) {
            return $attribution;
        }

        // Tracked page views always have a source
        $attribution->setSource($pageView->getSource() ?? SourceInterface::DIRECT);
        $attribution->setMedium($pageView->getMedium());
        $attribution->setCampaign($pageView->getCampaign());
        $attribution->setReferrer($pageView->getReferrer());
        $attribution->setLandingPage($pageView->getPage());
        $attribution->setTrackedAt($pageView->getCreatedAt());

        return $attribution;
    }
}
