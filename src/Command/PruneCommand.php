<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Command;

use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Setono\Doctrine\ORMTrait;
use Setono\SyliusConversionAttributionPlugin\Model\SourceInterface;
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
    use ORMTrait;

    public function __construct(
        ManagerRegistry $managerRegistry,
        /** @var class-string<SourceInterface> $sourceClass */
        private readonly string $sourceClass,
        // In days: a page view older than this can't be credited for an order anymore
        private readonly int $lookbackWindow,
    ) {
        parent::__construct();

        Assert::greaterThanEq($this->lookbackWindow, 1);

        $this->managerRegistry = $managerRegistry;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Pruning page views older than the lookback window');

        $total = (int) $this->getDeletableQueryBuilder()->select('COUNT(o)')->getQuery()->getSingleScalarResult();
        if ($total === 0) {
            $io->success('No page views to prune');

            return 0;
        }

        $io->progressStart($total);

        while (($deletableIds = $this->getDeletableIds()) !== []) {
            // A DQL DELETE returns the number of deleted rows
            $progress = $this
                ->getRepository($this->sourceClass)
                ->createQueryBuilder('o')
                ->delete()
                ->andWhere('o.id IN (:ids)')
                ->setParameter('ids', $deletableIds)
                ->getQuery()
                ->execute()
            ;
            Assert::integer($progress);

            $io->progressAdvance($progress);
        }

        $io->progressFinish();

        return 0;
    }

    /**
     * @return list<int>
     */
    private function getDeletableIds(): array
    {
        /** @var list<array{id: int}> $rows */
        $rows = $this
            ->getDeletableQueryBuilder()
            ->select('o.id')
            ->setMaxResults(1000)
            ->getQuery()
            ->getScalarResult()
        ;

        // getScalarResult() returns rows shaped like ['id' => x]; array_column() flattens them into
        // the list<int> that IN() expects. Combined with the 1000-row batch above this replaces the
        // previous one-DELETE-per-row loop (setMaxResults(1)).
        return array_column($rows, 'id');
    }

    private function getDeletableQueryBuilder(): QueryBuilder
    {
        return $this
            ->getRepository($this->sourceClass)
            ->createQueryBuilder('o')
            ->andWhere('o.createdAt < :date')
            ->setParameter('date', new \DateTimeImmutable(sprintf('-%d days', $this->lookbackWindow)))
        ;
    }
}
