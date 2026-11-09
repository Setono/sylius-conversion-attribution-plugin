<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Repository;

use Setono\SyliusConversionAttributionPlugin\Model\AttributionInterface;
use Setono\SyliusConversionAttributionPlugin\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderInterface as BaseOrderInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;

/**
 * @extends RepositoryInterface<AttributionInterface>
 */
interface AttributionRepositoryInterface extends RepositoryInterface
{
    public function findOneByOrder(BaseOrderInterface $order): ?AttributionInterface;

    /**
     * Completed orders with a client id and no attribution, oldest first, and only those completed since
     * $completedSince if it's given
     *
     * @return list<OrderInterface>
     */
    public function findOrdersToAttribute(int $limit, ?\DateTimeInterface $completedSince = null): array;
}
