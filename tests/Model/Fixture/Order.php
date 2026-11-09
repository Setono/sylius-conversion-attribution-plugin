<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\Model\Fixture;

use Doctrine\ORM\Mapping as ORM;
use Setono\SyliusConversionAttributionPlugin\Model\OrderInterface;
use Setono\SyliusConversionAttributionPlugin\Model\OrderTrait;
use Sylius\Component\Core\Model\Order as BaseOrder;

/**
 * An order like the one the README tells host applications to add, so OrderTraitTest tests the trait
 * where static analysis sees it used: it excludes the test application. Besides the README's attributes,
 * it's mapped with annotations too, so TraitMappingTest can load it with either driver
 *
 * @ORM\Entity
 *
 * @ORM\Table(name="sylius_order")
 */
#[ORM\Entity]
#[ORM\Table(name: 'sylius_order')]
final class Order extends BaseOrder implements OrderInterface
{
    use OrderTrait;
}
