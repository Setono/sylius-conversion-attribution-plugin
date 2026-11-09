<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\Model\Fixture;

use Doctrine\ORM\Mapping as ORM;
use Setono\SyliusConversionAttributionPlugin\Model\CustomerInterface;
use Setono\SyliusConversionAttributionPlugin\Model\CustomerTrait;
use Sylius\Component\Core\Model\Customer as BaseCustomer;

/**
 * A customer like the one the README tells host applications to add, so CustomerTraitTest tests the trait
 * where static analysis sees it used: it excludes the test application. Besides the README's attributes,
 * it's mapped with annotations too, so TraitMappingTest can load it with either driver
 *
 * @ORM\Entity
 *
 * @ORM\Table(name="sylius_customer")
 */
#[ORM\Entity]
#[ORM\Table(name: 'sylius_customer')]
final class Customer extends BaseCustomer implements CustomerInterface
{
    use CustomerTrait;
}
