<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\Command;

use Setono\SyliusConversionAttributionPlugin\Command\PruneCommand;
use Setono\SyliusConversionAttributionPlugin\Model\SourceInterface;
use Setono\SyliusConversionAttributionPlugin\Repository\SourceRepositoryInterface;
use Setono\SyliusConversionAttributionPlugin\Tests\Support\PageViewsAndOrders;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Runs against the test application with its default lookback window of 30 days
 */
final class PruneCommandTest extends KernelTestCase
{
    use PageViewsAndOrders;

    /**
     * @test
     */
    public function it_prunes_page_views_older_than_the_lookback_window(): void
    {
        self::trackPageView('client', 'google', new \DateTimeImmutable('-31 days'));
        $recent = self::trackPageView('client', 'facebook', new \DateTimeImmutable('-10 days'));

        $tester = $this->execute();

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('Pruning page views older than the lookback window', $tester->getDisplay());
        // the progress bar is finished, followed by a blank line
        self::assertMatchesRegularExpression('# 1/1 \[.+\] 100%\n\n$#u', $tester->getDisplay());
        self::assertSame([$recent->getId()], $this->remainingIds());
    }

    /**
     * @test
     */
    public function it_keeps_page_views_for_exactly_the_lookback_window(): void
    {
        $kept = self::trackPageView('client', 'google', new \DateTimeImmutable('-29 days -12 hours'));
        self::trackPageView('client', 'facebook', new \DateTimeImmutable('-30 days -12 hours'));

        $this->execute()->assertCommandIsSuccessful();

        self::assertSame([$kept->getId()], $this->remainingIds());
    }

    /**
     * @test
     */
    public function it_prunes_in_batches(): void
    {
        $pageView = self::trackPageView('client', 'google', new \DateTimeImmutable('-31 days'));
        // Copies of that page view, so there are more than a batch (1000) to prune
        $table = self::entityManager()->getClassMetadata($pageView::class)->getTableName();
        $connection = self::entityManager()->getConnection();
        for ($i = 0; $i < 1000; ++$i) {
            $connection->executeStatement(sprintf('INSERT INTO %1$s (client_id, source, created_at) SELECT client_id, source, created_at FROM %1$s WHERE id = ?', $table), [$pageView->getId()]);
        }

        $tester = $this->execute();

        $tester->assertCommandIsSuccessful();
        self::assertSame([], $this->remainingIds());
        self::assertMatchesRegularExpression('# 1001/1001 \[.+\] 100%#u', $tester->getDisplay());
    }

    /**
     * @test
     */
    public function it_reports_success_when_there_is_nothing_to_prune(): void
    {
        $recent = self::trackPageView('client', 'google', new \DateTimeImmutable('-1 day'));

        $tester = $this->execute();

        $tester->assertCommandIsSuccessful();
        self::assertSame([$recent->getId()], $this->remainingIds());
        self::assertStringContainsString('[OK] No page views to prune', $tester->getDisplay());
    }

    /**
     * @test
     */
    public function it_accepts_a_lookback_window_of_a_day(): void
    {
        self::assertSame('setono:sylius-conversion-attribution:prune', $this->createCommand(1)->getName());
    }

    /**
     * @test
     */
    public function it_rejects_a_lookback_window_shorter_than_a_day(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->createCommand(0);
    }

    private function createCommand(int $lookbackWindow): PruneCommand
    {
        $sourceRepository = self::getContainer()->get(SourceRepositoryInterface::class);
        self::assertInstanceOf(SourceRepositoryInterface::class, $sourceRepository);

        return new PruneCommand($sourceRepository, $lookbackWindow);
    }

    protected static function container(): ContainerInterface
    {
        return self::getContainer();
    }

    /**
     * @return list<mixed>
     */
    private function remainingIds(): array
    {
        $class = self::modelClass('setono_sylius_conversion_attribution.model.source.class', SourceInterface::class);

        self::entityManager()->clear();

        return array_map(static fn (SourceInterface $pageView): mixed => $pageView->getId(), self::entityManager()->getRepository($class)->findAll());
    }

    private function execute(): CommandTester
    {
        // getContainer() boots the kernel, unless the test already did
        self::getContainer();

        $tester = new CommandTester((new Application(self::$kernel))->find('setono:sylius-conversion-attribution:prune'));
        $tester->execute([]);

        return $tester;
    }
}
