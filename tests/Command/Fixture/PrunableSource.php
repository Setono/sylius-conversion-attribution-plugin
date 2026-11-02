<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\Command\Fixture;

use Doctrine\ORM\Mapping as ORM;
use Setono\SyliusConversionAttributionPlugin\Model\Source;

/**
 * A source entity, mapped like an application maps the plugin's Source model (a mapped superclass in the
 * plugin's XML mapping), created at a given time, to exercise PruneCommand against a real (in-memory) database
 */
#[ORM\Entity]
#[ORM\Table(name: 'test_prunable_source')]
class PrunableSource extends Source
{
    public function __construct(\DateTimeInterface $createdAt)
    {
        parent::__construct();

        $this->createdAt = $createdAt;
        $this->setClientId('client');
    }
}
