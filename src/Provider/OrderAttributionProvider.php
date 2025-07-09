<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Provider;

use Doctrine\Persistence\ManagerRegistry;
use Setono\Doctrine\ORMTrait;
use Setono\SyliusConversionAttributionPlugin\Model\OrderInterface;
use Setono\SyliusConversionAttributionPlugin\Model\SourceInterface;
use Webmozart\Assert\Assert;

final class OrderAttributionProvider implements OrderAttributionProviderInterface
{
    use ORMTrait;

    public function __construct(
        ManagerRegistry $managerRegistry,
        /** @var class-string<SourceInterface> $sourceClass */
        private readonly string $sourceClass,
        private readonly string $defaultSource,
    ) {
        $this->managerRegistry = $managerRegistry;
    }

    public function getSource(OrderInterface $order): ?SourceInterface
    {
        $clientId = $order->getClientId();
        if (null === $clientId) {
            return null;
        }

        $source = $this->getManager($this->sourceClass)
            ->createQueryBuilder()
            ->select('o')
            ->from($this->sourceClass, 'o')
            ->andWhere('o.clientId = :clientId')
            ->andWhere('o.source != :defaultSource')
            ->setParameter('clientId', $clientId)
            ->setParameter('defaultSource', $this->defaultSource)
            ->addOrderBy('o.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        Assert::nullOrIsInstanceOf($source, SourceInterface::class);

        return $source;
    }
}
