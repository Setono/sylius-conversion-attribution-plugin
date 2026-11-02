<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\Command;

use Setono\SyliusConversionAttributionPlugin\Command\BackfillCommand;
use Setono\SyliusConversionAttributionPlugin\Command\PruneCommand;
use Setono\SyliusConversionAttributionPlugin\Tests\Application\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\LazyCommand;

/**
 * Checks the plugin's service definitions register the commands in a Sylius application. It doesn't
 * execute them, since that needs the application's database: their own tests cover the behaviour.
 *
 * It covers nothing on purpose, so Infection doesn't run it for mutants: a mutant that changes a
 * command's constructor makes the kernel rebuild the whole container
 *
 * @coversNothing
 */
final class CommandRegistrationTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    /**
     * @test
     *
     * @dataProvider commands
     *
     * @param class-string $class
     */
    public function it_is_registered_in_the_application(string $name, string $class): void
    {
        $command = (new Application(self::bootKernel()))->find($name);
        if ($command instanceof LazyCommand) {
            $command = $command->getCommand();
        }

        self::assertInstanceOf($class, $command);
    }

    /**
     * @return iterable<string, array{string, class-string}>
     */
    public static function commands(): iterable
    {
        yield 'backfill' => ['setono:sylius-conversion-attribution:backfill', BackfillCommand::class];
        yield 'prune' => ['setono:sylius-conversion-attribution:prune', PruneCommand::class];
    }
}
