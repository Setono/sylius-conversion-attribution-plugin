<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Command;

use Setono\SyliusConversionAttributionPlugin\Repository\SourceRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Webmozart\Assert\Assert;

#[AsCommand(
    name: 'setono:sylius-conversion-attribution:prune',
    description: 'Deletes tracked page views older than the lookback window',
)]
final class PruneCommand extends Command
{
    private const BATCH_SIZE = 1000;

    public function __construct(
        private readonly SourceRepositoryInterface $sourceRepository,
        // In days: a page view older than this can't be credited for an order anymore
        private readonly int $lookbackWindow,
    ) {
        parent::__construct();

        Assert::greaterThanEq($this->lookbackWindow, 1);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Pruning page views older than the lookback window');

        $before = new \DateTimeImmutable(sprintf('-%d days', $this->lookbackWindow));

        $total = $this->sourceRepository->countTrackedBefore($before);
        if (0 === $total) {
            $io->success('No page views to prune');

            return Command::SUCCESS;
        }

        $io->progressStart($total);

        while (0 < $deleted = $this->sourceRepository->deleteTrackedBefore($before, self::BATCH_SIZE)) {
            $io->progressAdvance($deleted);
        }

        $io->progressFinish();

        return Command::SUCCESS;
    }
}
