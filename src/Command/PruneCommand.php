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
    description: 'Prunes old conversion attribution sources',
)]
final class PruneCommand extends Command
{
    use ORMTrait;

    public function __construct(
        ManagerRegistry $managerRegistry,
        /** @var class-string<SourceInterface> $sourceClass */
        private readonly string $sourceClass,
        private readonly int $daysToKeep = 180,
    ) {
        parent::__construct();

        Assert::greaterThanEq($this->daysToKeep, 0);

        $this->managerRegistry = $managerRegistry;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Pruning old conversion attribution sources');

        $total = (int) $this->getDeletableQueryBuilder()->select('COUNT(o)')->getQuery()->getSingleScalarResult();
        if ($total === 0) {
            $io->success('No sources to prune');

            return 0;
        }

        $io->progressStart($total);

        while (($deletableIds = $this->getDeletableIds()) !== []) {
            $progress = (int) $this
                ->getRepository($this->sourceClass)
                ->createQueryBuilder('o')
                ->delete()
                ->andWhere('o.id IN (:ids)')
                ->setParameter('ids', $deletableIds)
                ->getQuery()
                ->execute()
            ;

            $io->progressAdvance($progress);
        }

        $io->progressFinish();

        return 0;
    }

    private function getDeletableIds(): array
    {
        return $this
            ->getDeletableQueryBuilder()
            ->select('o.id')
            ->setMaxResults(1)
            ->getQuery()
            ->getScalarResult()
        ;
    }

    private function getDeletableQueryBuilder(): QueryBuilder
    {
        return $this
            ->getRepository($this->sourceClass)
            ->createQueryBuilder('o')
            ->andWhere('o.createdAt < :date')
            ->setParameter('date', new \DateTimeImmutable(sprintf('-%d days', $this->daysToKeep)))
        ;
    }
}
