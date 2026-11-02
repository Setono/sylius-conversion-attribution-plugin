<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\Support;

use Doctrine\ORM\EntityManagerInterface;
use Setono\SyliusConversionAttributionPlugin\Model\AttributionInterface;
use Setono\SyliusConversionAttributionPlugin\Model\OrderInterface;
use Setono\SyliusConversionAttributionPlugin\Model\SourceInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Creates tracked page views, orders and attributions in the test application's database, through the
 * application's own factories. dama/doctrine-test-bundle rolls them back after each test
 */
trait PageViewsAndOrders
{
    abstract protected static function container(): ContainerInterface;

    protected static function entityManager(): EntityManagerInterface
    {
        $entityManager = static::container()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return $entityManager;
    }

    /**
     * @param array{medium?: string, campaign?: string, referrer?: string, page?: string} $details
     */
    protected static function trackPageView(string $clientId, string $source, \DateTimeInterface $trackedAt, array $details = []): SourceInterface
    {
        $pageView = self::factory('setono_sylius_conversion_attribution.factory.source')->createNew();
        self::assertInstanceOf(SourceInterface::class, $pageView);
        $pageView->setClientId($clientId);
        $pageView->setSource($source);
        $pageView->setMedium($details['medium'] ?? null);
        $pageView->setCampaign($details['campaign'] ?? null);
        $pageView->setReferrer($details['referrer'] ?? null);
        $pageView->setPage($details['page'] ?? null);

        $entityManager = self::entityManager();
        $entityManager->persist($pageView);
        $entityManager->flush();

        // A page view is stamped with the time it's created, so move it to when it was tracked
        $entityManager
            ->createQuery(sprintf('UPDATE %s s SET s.createdAt = :trackedAt WHERE s.id = :id', $pageView::class))
            ->setParameter('trackedAt', \DateTime::createFromInterface($trackedAt), 'datetime')
            ->setParameter('id', $pageView->getId())
            ->execute()
        ;
        $entityManager->refresh($pageView);

        return $pageView;
    }

    /**
     * An order completed at $checkoutCompletedAt, or one being completed if that's null
     */
    protected static function order(?string $clientId, ?\DateTimeInterface $checkoutCompletedAt): OrderInterface
    {
        $order = self::factory('sylius.factory.order')->createNew();
        self::assertInstanceOf(OrderInterface::class, $order);
        $order->setCurrencyCode('USD');
        $order->setLocaleCode('en_US');
        $order->setCheckoutCompletedAt($checkoutCompletedAt);
        if (null !== $clientId) {
            $order->setClientId($clientId);
        }

        $entityManager = self::entityManager();
        $entityManager->persist($order);
        $entityManager->flush();

        return $order;
    }

    /**
     * @param array{medium?: string, campaign?: string, referrer?: string, landingPage?: string, trackedAt?: string} $details
     */
    protected static function attribution(OrderInterface $order, string $source, array $details = []): AttributionInterface
    {
        $attribution = self::factory('setono_sylius_conversion_attribution.factory.attribution')->createNew();
        self::assertInstanceOf(AttributionInterface::class, $attribution);
        $attribution->setOrder($order);
        $attribution->setSource($source);
        $attribution->setMedium($details['medium'] ?? null);
        $attribution->setCampaign($details['campaign'] ?? null);
        $attribution->setReferrer($details['referrer'] ?? null);
        $attribution->setLandingPage($details['landingPage'] ?? null);
        $attribution->setTrackedAt(new \DateTimeImmutable($details['trackedAt'] ?? '2026-10-26 11:00:00'));

        $entityManager = self::entityManager();
        $entityManager->persist($attribution);
        $entityManager->flush();

        return $attribution;
    }

    /**
     * @return list<AttributionInterface>
     */
    protected static function attributionsOf(OrderInterface $order): array
    {
        $class = self::modelClass('setono_sylius_conversion_attribution.model.attribution.class', AttributionInterface::class);

        return array_values(self::entityManager()->getRepository($class)->findBy(['order' => $order->getId()]));
    }

    /**
     * The class configured in a container parameter, e.g. the application's model class for a resource
     *
     * @template T of object
     *
     * @param class-string<T> $type
     *
     * @return class-string<T>
     */
    protected static function modelClass(string $parameter, string $type): string
    {
        $class = static::container()->getParameter($parameter);
        if (!is_string($class) || !is_a($class, $type, true)) {
            self::fail(sprintf('The parameter "%s" is not a %s class', $parameter, $type));
        }

        return $class;
    }

    /**
     * @return FactoryInterface<object>
     */
    private static function factory(string $id): FactoryInterface
    {
        $factory = static::container()->get($id);
        self::assertInstanceOf(FactoryInterface::class, $factory);

        return $factory;
    }
}
