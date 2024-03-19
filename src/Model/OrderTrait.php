<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Model;

use Doctrine\ORM\Mapping as ORM;

trait OrderTrait
{
    /** @ORM\Column(type="string", nullable=true) */
    protected ?string $clientId = null;

    public function getClientId(): ?string
    {
        return $this->clientId;
    }

    public function setClientId(string $clientId): void
    {
        $this->clientId = $clientId;
    }
}
