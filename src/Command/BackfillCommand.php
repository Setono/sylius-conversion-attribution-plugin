<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Command;

use Doctrine\Common\Collections\Criteria;
use Doctrine\Persistence\ManagerRegistry;
use Setono\Doctrine\ORMTrait;
use Setono\SyliusConversionAttributionPlugin\Attribution\OrderAttributorInterface;
use Setono\SyliusConversionAttributionPlugin\Model\AttributionInterface;
use Setono\SyliusConversionAttributionPlugin\Model\OrderInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Orders completed before the plugin recorded attributions at checkout have none. This records them, as of
 * each order's checkout, from the page views still tracked. Run it before the prune command deletes them
 */
#[AsCommand(
    name: 'setono:sylius-conversion-attribution:backfill',
    description: 'Attributes completed orders that have no attribution yet (run it before pruning)',
)]
final class BackfillCommand extends Command
{
    use ORMTrait;

    public function __construct(
        ManagerRegistry $managerRegistry,
        private readonly OrderAttributorInterface $orderAttributor,
        /** @var class-string<OrderInterface> $orderClass */
        private readonly string $orderClass,
        /** @var class-string<AttributionInterface> $attributionClass */
        private readonly string $attributionClass,
        // How many orders are attributed and flushed at a time
        private readonly int $batchSize,
    ) {
        parent::__construct();

        $this->managerRegistry = $managerRegistry;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Attributing completed orders');

        $total = $this->getRepository($this->orderClass)->matching($this->trackedOrders())->count();
        if (0 === $total) {
            $io->success('No orders to attribute');

            return 0;
        }

        $io->progressStart($total);

        $manager = $this->getManager($this->orderClass);
        $unattributed = 0;
        $attributed = 0;
        $lastId = null;

        // Orders that get no attribution are passed by id, so a batch never starts over with them
        while ([] !== $orders = $this->getNextOrders($lastId)) {
            foreach ($orders as $order) {
                $lastId = $order->getId();

                if (null === $this->getRepository($this->attributionClass)->findOneBy(['order' => $order])) {
                    ++$unattributed;
                    if (null !== $this->orderAttributor->attribute($order)) {
                        ++$attributed;
                    }
                }
            }

            $manager->flush();
            $manager->clear();

            $io->progressAdvance(count($orders));
        }

        $io->progressFinish();
        $io->success(sprintf('Attributed %d of the %d orders without an attribution', $attributed, $unattributed));

        return 0;
    }

    /**
     * @return array<int, OrderInterface>
     */
    private function getNextOrders(mixed $lastId): array
    {
        $criteria = $this->trackedOrders()->orderBy(['id' => Criteria::ASC])->setMaxResults($this->batchSize);
        if (null !== $lastId) {
            $criteria->andWhere(Criteria::expr()->gt('id', $lastId));
        }

        return $this->getRepository($this->orderClass)->matching($criteria)->toArray();
    }

    /**
     * Completed orders that have a client id
     */
    private function trackedOrders(): Criteria
    {
        return Criteria::create()
            ->where(Criteria::expr()->neq('checkoutCompletedAt', null))
            ->andWhere(Criteria::expr()->neq('clientId', null))
        ;
    }
}
