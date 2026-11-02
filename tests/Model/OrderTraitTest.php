<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\Model;

use PHPUnit\Framework\TestCase;
use Setono\SyliusConversionAttributionPlugin\Tests\Application\Model\Order;

final class OrderTraitTest extends TestCase
{
    /**
     * @test
     */
    public function it_has_no_client_id_by_default(): void
    {
        self::assertNull((new Order())->getClientId());
    }

    /**
     * @test
     */
    public function it_sets_the_client_id(): void
    {
        $order = new Order();
        $order->setClientId('client');

        self::assertSame('client', $order->getClientId());
    }
}
