<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Model;

use Sylius\Component\Core\Model\CustomerInterface as BaseCustomerInterface;

interface CustomerInterface extends BaseCustomerInterface
{
    /**
     * The client ids of the browsers this customer checked out from or logged in to the shop on
     *
     * @return list<string>
     */
    public function getClientIds(): array;

    /**
     * Returns false if the customer already had the client id
     */
    public function addClientId(string $clientId): bool;
}
