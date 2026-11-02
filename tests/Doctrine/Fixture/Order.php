<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\Doctrine\Fixture;

use Setono\SyliusConversionAttributionPlugin\Model\OrderInterface;
use Setono\SyliusConversionAttributionPlugin\Model\OrderTrait;
use Sylius\Component\Core\Model\Order as BaseOrder;

/**
 * An order set up the way the README tells host applications to, mapped with only the columns the plugin
 * reads (Order.orm.xml)
 */
class Order extends BaseOrder implements OrderInterface
{
    use OrderTrait;

    public static function completed(?string $clientId, \DateTimeInterface $checkoutCompletedAt): self
    {
        $order = new self();
        $order->clientId = $clientId;
        $order->checkoutCompletedAt = $checkoutCompletedAt;

        return $order;
    }

    /**
     * An order being completed: Sylius sets its checkout completion time afterwards
     */
    public static function inCheckout(string $clientId): self
    {
        $order = new self();
        $order->clientId = $clientId;

        return $order;
    }
}
