<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Model;

use Doctrine\ORM\Mapping as ORM;

trait CustomerTrait
{
    /**
     * @ORM\Column(type="json", nullable=true)
     *
     * @var list<string>
     */
    #[ORM\Column(type: 'json', nullable: true)]
    protected ?array $clientIds = [];

    /**
     * @return list<string>
     */
    public function getClientIds(): array
    {
        return $this->clientIds ?? [];
    }

    public function addClientId(string $clientId): bool
    {
        if (null === $this->clientIds) {
            $this->clientIds = [];
        }

        if (in_array($clientId, $this->clientIds, true)) {
            return false;
        }

        $this->clientIds[] = $clientId;

        return true;
    }
}
