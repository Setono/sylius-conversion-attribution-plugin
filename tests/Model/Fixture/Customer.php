<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\Model\Fixture;

use Setono\SyliusConversionAttributionPlugin\Model\CustomerInterface;
use Setono\SyliusConversionAttributionPlugin\Model\CustomerTrait;
use Sylius\Component\Core\Model\Customer as BaseCustomer;

/**
 * A customer set up the way the README tells host applications to, so CustomerTraitTest tests the trait
 * where static analysis sees it used: it excludes the test application
 */
final class Customer extends BaseCustomer implements CustomerInterface
{
    use CustomerTrait;
}
