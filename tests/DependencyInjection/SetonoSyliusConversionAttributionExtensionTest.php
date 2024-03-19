<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\DependencyInjection;

use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Setono\SyliusConversionAttributionPlugin\DependencyInjection\SetonoSyliusConversionAttributionExtension;

final class SetonoSyliusConversionAttributionExtensionTest extends AbstractExtensionTestCase
{
    protected function getContainerExtensions(): array
    {
        return [
            new SetonoSyliusConversionAttributionExtension(),
        ];
    }

    /**
     * @test
     */
    public function after_loading_the_correct_parameter_has_been_set(): void
    {
        $this->load();

        $this->assertContainerBuilderHasParameter('setono_sylius_conversion_attribution.session_timeout', 1800);
    }
}
