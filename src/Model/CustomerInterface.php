<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Model;

use Sylius\Component\Core\Model\CustomerInterface as BaseCustomerInterface;

interface CustomerInterface extends BaseCustomerInterface
{
    /**
     * The client ids of the browsers this customer checked out from
     *
     * @return list<string>
     */
    public function getClientIds(): array;

    public function addClientId(string $clientId): void;
}
