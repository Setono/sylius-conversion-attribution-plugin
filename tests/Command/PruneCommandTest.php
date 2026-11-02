<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\Command;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Setono\SyliusConversionAttributionPlugin\Command\PruneCommand;
use Setono\SyliusConversionAttributionPlugin\Tests\Command\Fixture\PrunableSource;
use Symfony\Component\Console\Tester\CommandTester;

final class PruneCommandTest extends TestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $config = ORMSetup::createAttributeMetadataConfiguration([__DIR__ . '/Fixture'], true);
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config);

        // The public constructor works in both doctrine/orm 2.x and 3.x; the 2.x EntityManager::create()
        // factory was removed in 3.x, so it is deliberately avoided here
        $this->em = new EntityManager($connection, $config);

        $schemaTool = new SchemaTool($this->em);
        $schemaTool->createSchema([$this->em->getClassMetadata(PrunableSource::class)]);
    }

    /**
     * @test
     */
    public function it_prunes_only_rows_older_than_the_retention_window(): void
    {
        $old = new PrunableSource(new \DateTimeImmutable('-200 days'));
        $recent = new PrunableSource(new \DateTimeImmutable('-10 days'));
        $this->em->persist($old);
        $this->em->persist($recent);
        $this->em->flush();
        $recentId = $recent->getId();
        $this->em->clear();

        $tester = new CommandTester($this->createCommand(180));
        $exitCode = $tester->execute([]);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Pruning old conversion attribution sources', $tester->getDisplay());
        // the progress bar is finished, followed by a blank line
        self::assertMatchesRegularExpression('# 1/1 \[.+\] 100%\n\n$#u', $tester->getDisplay());

        // The command terminates (the pre-fix loop bound to getScalarResult()'s row shape never did)
        // and leaves exactly the recent row behind
        $remaining = $this->em->getRepository(PrunableSource::class)->findAll();
        self::assertCount(1, $remaining);

        $survivor = reset($remaining);
        self::assertInstanceOf(PrunableSource::class, $survivor);
        self::assertSame($recentId, $survivor->getId());
    }

    /**
     * @test
     */
    public function it_reports_success_when_there_is_nothing_to_prune(): void
    {
        $this->em->persist(new PrunableSource(new \DateTimeImmutable('-1 day')));
        $this->em->flush();
        $this->em->clear();

        $tester = new CommandTester($this->createCommand(180));

        self::assertSame(0, $tester->execute([]));
        self::assertCount(1, $this->em->getRepository(PrunableSource::class)->findAll());
        self::assertStringContainsString('Pruning old conversion attribution sources', $tester->getDisplay());
        self::assertStringContainsString('[OK] No sources to prune', $tester->getDisplay());
    }

    /**
     * @test
     */
    public function it_keeps_180_days_by_default(): void
    {
        $kept = new PrunableSource(new \DateTimeImmutable('-179 days -12 hours'));
        $this->em->persist($kept);
        $this->em->persist(new PrunableSource(new \DateTimeImmutable('-180 days -12 hours')));
        $this->em->flush();
        $keptId = $kept->getId();
        $this->em->clear();

        (new CommandTester($this->createCommand()))->execute([]);

        $remaining = $this->em->getRepository(PrunableSource::class)->findAll();
        self::assertCount(1, $remaining);
        self::assertSame($keptId, reset($remaining)->getId());
    }

    /**
     * @test
     */
    public function it_prunes_everything_tracked_before_now_when_keeping_zero_days(): void
    {
        $this->em->persist(new PrunableSource(new \DateTimeImmutable('-1 minute')));
        $this->em->flush();
        $this->em->clear();

        (new CommandTester($this->createCommand(0)))->execute([]);

        self::assertCount(0, $this->em->getRepository(PrunableSource::class)->findAll());
    }

    /**
     * @test
     */
    public function it_rejects_a_negative_number_of_days(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->createCommand(-1);
    }

    private function createCommand(?int $daysToKeep = null): PruneCommand
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($this->em);

        // PrunableSource is a deliberately minimal stand-in (only id + createdAt) for a real source entity
        /** @psalm-suppress InvalidArgument */
        return null === $daysToKeep
            ? new PruneCommand($registry, PrunableSource::class)
            : new PruneCommand($registry, PrunableSource::class, $daysToKeep);
    }
}
