<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Repository;

use Setono\SyliusConversionAttributionPlugin\Model\SourceInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;

/**
 * @extends RepositoryInterface<SourceInterface>
 */
interface SourceRepositoryInterface extends RepositoryInterface
{
    /**
     * The newest non-direct page view tracked for the client id between $from and $until (both included) or, if
     * there is none, the newest direct one
     */
    public function findPageViewToCredit(string $clientId, \DateTimeInterface $from, \DateTimeInterface $until): ?SourceInterface;

    public function countTrackedBefore(\DateTimeInterface $before): int;

    /**
     * Deletes up to $limit page views tracked before $before and returns how many it deleted
     */
    public function deleteTrackedBefore(\DateTimeInterface $before, int $limit): int;
}
