<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\Command;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Setono\SyliusConversionAttributionPlugin\Command\PruneCommand;
use Setono\SyliusConversionAttributionPlugin\Tests\Doctrine\Fixture\TrackedSource;
use Setono\SyliusConversionAttributionPlugin\Tests\Doctrine\InMemoryEntityManager;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class PruneCommandTest extends TestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->em = InMemoryEntityManager::create();
    }

    /**
     * @test
     */
    public function it_prunes_page_views_older_than_the_lookback_window(): void
    {
        $this->track('-31 days');
        $recent = $this->track('-10 days');

        $tester = new CommandTester($this->createCommand(30));
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('Pruning page views older than the lookback window', $tester->getDisplay());
        // the progress bar is finished, followed by a blank line
        self::assertMatchesRegularExpression('# 1/1 \[.+\] 100%\n\n$#u', $tester->getDisplay());
        self::assertSame([$recent], $this->remainingIds());
    }

    /**
     * @test
     */
    public function it_keeps_page_views_for_exactly_the_lookback_window(): void
    {
        $kept = $this->track('-29 days -12 hours');
        $this->track('-30 days -12 hours');

        $tester = new CommandTester($this->createCommand(30));
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        self::assertSame([$kept], $this->remainingIds());
    }

    /**
     * @test
     */
    public function it_prunes_in_batches(): void
    {
        for ($i = 0; $i < 1001; ++$i) {
            $this->em->persist(TrackedSource::at(new \DateTimeImmutable('-31 days')));
        }
        $this->em->flush();

        $tester = new CommandTester($this->createCommand(30));
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        self::assertSame([], $this->remainingIds());
        self::assertMatchesRegularExpression('# 1001/1001 \[.+\] 100%#u', $tester->getDisplay());
    }

    /**
     * @test
     */
    public function it_reports_success_when_there_is_nothing_to_prune(): void
    {
        $recent = $this->track('-1 day');

        $tester = new CommandTester($this->createCommand(30));
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        self::assertSame([$recent], $this->remainingIds());
        self::assertStringContainsString('[OK] No page views to prune', $tester->getDisplay());
    }

    /**
     * @test
     */
    public function it_accepts_a_lookback_window_of_a_day(): void
    {
        $this->track('-2 days');
        $recent = $this->track('-12 hours');

        $tester = new CommandTester($this->createCommand(1));
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        self::assertSame([$recent], $this->remainingIds());
    }

    /**
     * @test
     */
    public function it_rejects_a_lookback_window_shorter_than_a_day(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->createCommand(0);
    }

    private function track(string $createdAt): ?int
    {
        $trackedSource = TrackedSource::at(new \DateTimeImmutable($createdAt));
        $this->em->persist($trackedSource);
        $this->em->flush();

        return $trackedSource->getId();
    }

    /**
     * @return list<int|null>
     */
    private function remainingIds(): array
    {
        $this->em->clear();

        return array_map(
            static fn (TrackedSource $trackedSource): ?int => $trackedSource->getId(),
            $this->em->getRepository(TrackedSource::class)->findAll(),
        );
    }

    private function createCommand(int $lookbackWindow): Command
    {
        $this->em->clear();

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($this->em);

        // Look the command up by its name, like the console does, so the name is tested too. Executing it
        // needs no kernel: CommandRegistrationTest checks that the plugin registers it in an application
        $application = new Application();
        $application->add(new PruneCommand($registry, TrackedSource::class, $lookbackWindow));

        return $application->find('setono:sylius-conversion-attribution:prune');
    }
}
