<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\Twig;

use Setono\SyliusConversionAttributionPlugin\Twig\Extension;
use Twig\Test\IntegrationTestCase;

final class ExtensionTest extends IntegrationTestCase
{
    use AttributedOrders;

    protected function getExtensions(): array
    {
        return [new Extension()];
    }

    protected static function getFixturesDirectory(): string
    {
        return __DIR__ . '/Fixtures/Extension';
    }
}
