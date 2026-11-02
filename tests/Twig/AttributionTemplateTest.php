<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\Twig;

use Setono\SyliusConversionAttributionPlugin\Twig\Extension;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Test\IntegrationTestCase;

/**
 * Renders the attribution box of the admin order page with the test application's translator. The fixtures
 * (in Fixtures/AttributionTemplate) include the plugin's real template by its name
 */
final class AttributionTemplateTest extends IntegrationTestCase
{
    use AttributedOrders;

    private const TEMPLATE = '@SetonoSyliusConversionAttributionPlugin/order/attribution.html.twig';

    protected function getExtensions(): array
    {
        $translator = self::container()->get('translator');
        self::assertInstanceOf(TranslatorInterface::class, $translator);

        return [new Extension(), new TranslationExtension($translator)];
    }

    protected static function getFixturesDirectory(): string
    {
        return __DIR__ . '/Fixtures/AttributionTemplate';
    }

    /**
     * Adds the plugin's template to the ones the fixture defines, as the integration test case only loads
     * templates from the fixture
     */
    protected function doIntegrationTest(mixed $file, mixed $message, mixed $condition, mixed $templateSources, mixed $exception, mixed $outputs, mixed $deprecation = ''): void
    {
        if (!is_array($templateSources)) {
            throw new \LogicException('The integration test case passes the fixture\'s templates as an array');
        }

        $templateSources[self::TEMPLATE] = (string) file_get_contents(dirname(__DIR__, 2) . '/src/Resources/views/order/attribution.html.twig');

        parent::doIntegrationTest($file, $message, $condition, $templateSources, $exception, $outputs, $deprecation);
    }
}
