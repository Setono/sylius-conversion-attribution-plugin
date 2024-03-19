<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Model;

use Sylius\Component\Core\Model\CustomerInterface as BaseCustomerInterface;

interface CustomerInterface extends BaseCustomerInterface
{
    /**
     * Returns a list of all client ids this customer has had
     *
     * @return list<string>
     */
    public function getClientIds(): array;

    public function addClientId(string $clientId): void;
}
