<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\Application\Model;

use Doctrine\ORM\Mapping as ORM;
use Setono\SyliusConversionAttributionPlugin\Model\CustomerInterface;
use Setono\SyliusConversionAttributionPlugin\Model\CustomerTrait;
use Sylius\Component\Core\Model\Customer as BaseCustomer;

/**
 * @ORM\Entity
 *
 * @ORM\Table(name="sylius_customer")
 */
class Customer extends BaseCustomer implements CustomerInterface
{
    use CustomerTrait;
}
