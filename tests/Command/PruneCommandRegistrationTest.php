<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\Command;

use Setono\SyliusConversionAttributionPlugin\Command\PruneCommand;
use Setono\SyliusConversionAttributionPlugin\Tests\Application\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\LazyCommand;

/**
 * Checks the plugin's service definition registers the command in a Sylius application. It doesn't
 * execute it, since that needs the application's database: PruneCommandTest covers the behaviour.
 *
 * It covers nothing on purpose, so Infection doesn't run it for mutants: a mutant that changes the
 * command's constructor makes the kernel rebuild the whole container
 *
 * @coversNothing
 */
final class PruneCommandRegistrationTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    /**
     * @test
     */
    public function it_is_registered_in_the_application(): void
    {
        $command = (new Application(self::bootKernel()))->find('setono:sylius-conversion-attribution:prune');
        if ($command instanceof LazyCommand) {
            $command = $command->getCommand();
        }

        self::assertInstanceOf(PruneCommand::class, $command);
        self::assertSame('Prunes old conversion attribution sources', $command->getDescription());
    }
}
