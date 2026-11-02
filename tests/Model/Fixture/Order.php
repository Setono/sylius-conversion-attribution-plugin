<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\Model\Fixture;

use Setono\SyliusConversionAttributionPlugin\Model\OrderInterface;
use Setono\SyliusConversionAttributionPlugin\Model\OrderTrait;
use Sylius\Component\Core\Model\Order as BaseOrder;

/**
 * An order set up the way the README tells host applications to, so OrderTraitTest tests the trait
 * where static analysis sees it used: it excludes the test application
 */
final class Order extends BaseOrder implements OrderInterface
{
    use OrderTrait;
}
