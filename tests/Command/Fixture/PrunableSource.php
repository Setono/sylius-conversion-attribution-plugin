<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\Command\Fixture;

use Doctrine\ORM\Mapping as ORM;

/**
 * A minimal entity that mirrors the fields PruneCommand touches (id + createdAt), used to exercise
 * the command against a real (in-memory) database.
 */
#[ORM\Entity]
#[ORM\Table(name: 'test_prunable_source')]
class PrunableSource
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    public function __construct(\DateTimeInterface $createdAt)
    {
        $this->createdAt = $createdAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }
}
