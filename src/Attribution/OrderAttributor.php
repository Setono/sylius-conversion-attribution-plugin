<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Attribution;

use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Expr\Comparison;
use Doctrine\Persistence\ManagerRegistry;
use Setono\Doctrine\ORMTrait;
use Setono\SyliusConversionAttributionPlugin\Model\AttributionInterface;
use Setono\SyliusConversionAttributionPlugin\Model\OrderInterface;
use Setono\SyliusConversionAttributionPlugin\Model\SourceInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;

final class OrderAttributor implements OrderAttributorInterface
{
    use ORMTrait;

    public function __construct(
        ManagerRegistry $managerRegistry,
        /** @var FactoryInterface<covariant AttributionInterface> $attributionFactory */
        private readonly FactoryInterface $attributionFactory,
        /** @var class-string<SourceInterface> $sourceClass */
        private readonly string $sourceClass,
        // In days
        private readonly int $lookbackWindow,
    ) {
        $this->managerRegistry = $managerRegistry;
    }

    public function attribute(OrderInterface $order): ?AttributionInterface
    {
        $clientId = $order->getClientId();
        if (null === $clientId) {
            return null;
        }

        // Sylius sets the checkout completion time after the order is completed, so an order being completed has none
        $completedAt = $order->getCheckoutCompletedAt() ?? new \DateTimeImmutable();

        $pageView = $this->findNewestPageView($clientId, $completedAt, Criteria::expr()->neq('source', SourceInterface::DIRECT))
            ?? $this->findNewestPageView($clientId, $completedAt, Criteria::expr()->eq('source', SourceInterface::DIRECT));
        if (null === $pageView) {
            return null;
        }

        $attribution = $this->attributionFactory->createNew();
        $attribution->setOrder($order);
        // The page views found above all have a source
        $attribution->setSource($pageView->getSource() ?? SourceInterface::DIRECT);
        $attribution->setMedium($pageView->getMedium());
        $attribution->setCampaign($pageView->getCampaign());
        $attribution->setReferrer($pageView->getReferrer());
        $attribution->setLandingPage($pageView->getPage());
        $attribution->setTrackedAt($pageView->getCreatedAt());

        $this->getManager($attribution)->persist($attribution);

        return $attribution;
    }

    private function findNewestPageView(string $clientId, \DateTimeInterface $completedAt, Comparison $source): ?SourceInterface
    {
        $windowStart = \DateTimeImmutable::createFromInterface($completedAt)->modify(sprintf('-%d days', $this->lookbackWindow));

        $pageView = $this->getRepository($this->sourceClass)->matching(
            Criteria::create()
                ->where(Criteria::expr()->eq('clientId', $clientId))
                ->andWhere($source)
                ->andWhere(Criteria::expr()->gte('createdAt', \DateTime::createFromImmutable($windowStart)))
                ->andWhere(Criteria::expr()->lte('createdAt', \DateTime::createFromInterface($completedAt)))
                ->orderBy(['createdAt' => Criteria::DESC, 'id' => Criteria::DESC])
                ->setMaxResults(1),
        )->first();

        return false === $pageView ? null : $pageView;
    }
}
