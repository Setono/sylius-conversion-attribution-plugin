<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Attribution;

use Setono\SyliusConversionAttributionPlugin\Model\AttributionInterface;
use Setono\SyliusConversionAttributionPlugin\Model\OrderInterface;

interface OrderAttributorInterface
{
    /**
     * Credits the order to the newest non-direct page view tracked for its client id in the lookback window
     * before its checkout completion (now, for an order being completed) or, if there is none, to the newest
     * direct one, and persists the attribution (without flushing). Returns null and persists nothing if the
     * order has no client id or no page view was tracked for it in the window
     */
    public function attribute(OrderInterface $order): ?AttributionInterface;
}
