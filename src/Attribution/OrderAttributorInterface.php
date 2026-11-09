<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Attribution;

use Setono\SyliusConversionAttributionPlugin\Model\AttributionInterface;
use Setono\SyliusConversionAttributionPlugin\Model\OrderInterface;

interface OrderAttributorInterface
{
    /**
     * Credits a completed order to the newest non-direct page view tracked for its client id in the lookback
     * window before checkout or, if there is none, to the newest direct one or, failing that, to nothing.
     * The attribution isn't persisted
     *
     * @throws \InvalidArgumentException if the order isn't completed or has no client id
     */
    public function attribute(OrderInterface $order): AttributionInterface;
}
