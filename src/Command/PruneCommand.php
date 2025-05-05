<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Command;

use Doctrine\Persistence\ManagerRegistry;
use Setono\Doctrine\ORMTrait;
use Setono\SyliusConversionAttributionPlugin\Model\SourceInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
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
        $this
            ->getRepository($this->sourceClass)
            ->createQueryBuilder('o')
            ->delete()
            ->andWhere('o.createdAt < :date')
            ->setParameter('date', new \DateTimeImmutable(sprintf('-%d days', $this->daysToKeep)))
            ->getQuery()
            ->execute()
        ;

        return 0;
    }
}
