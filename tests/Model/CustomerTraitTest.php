<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\Model;

use PHPUnit\Framework\TestCase;
use Setono\SyliusConversionAttributionPlugin\Tests\Application\Model\Customer;

final class CustomerTraitTest extends TestCase
{
    /**
     * @test
     */
    public function it_has_no_client_ids_by_default(): void
    {
        self::assertSame([], (new Customer())->getClientIds());
    }

    /**
     * @test
     */
    public function it_adds_each_client_id_once(): void
    {
        $customer = new Customer();
        $customer->addClientId('a');
        $customer->addClientId('b');
        $customer->addClientId('a');

        self::assertSame(['a', 'b'], $customer->getClientIds());
    }

    /**
     * @test
     */
    public function it_compares_client_ids_strictly(): void
    {
        // Loosely, numeric strings are compared as numbers, and '10' == '1e1'
        $customer = new Customer();
        $customer->addClientId('10');
        $customer->addClientId('1e1');

        self::assertSame(['10', '1e1'], $customer->getClientIds());
    }

    /**
     * @test
     */
    public function it_handles_a_null_column(): void
    {
        // The column is nullable, so Doctrine may hydrate null instead of an array
        $customer = new Customer();
        (new \ReflectionProperty(Customer::class, 'clientIds'))->setValue($customer, null);

        self::assertSame([], $customer->getClientIds());

        $customer->addClientId('a');
        self::assertSame(['a'], $customer->getClientIds());
    }
}
