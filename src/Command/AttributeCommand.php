<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Command;

use Doctrine\Persistence\ManagerRegistry;
use Setono\Doctrine\ORMTrait;
use Setono\SyliusConversionAttributionPlugin\Attribution\OrderAttributorInterface;
use Setono\SyliusConversionAttributionPlugin\Repository\AttributionRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Lock\LockFactory;

/**
 * Attributing orders apart from the checkout means it can never make a checkout fail. Schedule it every few
 * minutes; the first run after upgrading attributes the orders completed before
 */
#[AsCommand(
    name: self::NAME,
    description: 'Attributes the completed orders that have no attribution yet',
)]
final class AttributeCommand extends Command
{
    use ORMTrait;

    public const NAME = 'setono:sylius-conversion-attribution:attribute';

    private const BATCH_SIZE = 100;

    public function __construct(
        ManagerRegistry $managerRegistry,
        private readonly OrderAttributorInterface $orderAttributor,
        private readonly AttributionRepositoryInterface $attributionRepository,
        private readonly LockFactory $lockFactory,
        // In days
        private readonly int $lookbackWindow,
    ) {
        parent::__construct();

        $this->managerRegistry = $managerRegistry;
    }

    protected function configure(): void
    {
        $this->addOption('all', null, InputOption::VALUE_NONE, 'Also attribute orders completed before the lookback window, e.g. once after upgrading');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // The application's lock store (framework.lock), so runs on several servers can share it
        $lock = $this->lockFactory->createLock(self::NAME);
        if (!$lock->acquire()) {
            $io->note('The command is already running in another process');

            return Command::SUCCESS;
        }

        try {
            // An order completed before the lookback window has no page views left to credit once they're pruned
            $attributed = $this->attribute(true === $input->getOption('all') ? null : new \DateTimeImmutable(sprintf('-%d days', $this->lookbackWindow)));
        } finally {
            $lock->release();
        }

        $io->success(0 === $attributed ? 'No orders to attribute' : sprintf('Orders attributed: %d', $attributed));

        return Command::SUCCESS;
    }

    private function attribute(?\DateTimeInterface $completedSince): int
    {
        $manager = $this->getManager($this->attributionRepository->getClassName());
        $attributed = 0;

        // Every order gets an attribution, so the next batch never contains an order from this one
        while ([] !== $orders = $this->attributionRepository->findOrdersToAttribute(self::BATCH_SIZE, $completedSince)) {
            foreach ($orders as $order) {
                $manager->persist($this->orderAttributor->attribute($order));
                ++$attributed;
            }

            $manager->flush();
            $manager->clear();
        }

        return $attributed;
    }
}
