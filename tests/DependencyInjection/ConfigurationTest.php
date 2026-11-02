<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Setono\SyliusConversionAttributionPlugin\DependencyInjection\Configuration;
use Symfony\Component\Config\Definition\Processor;

final class ConfigurationTest extends TestCase
{
    /**
     * @test
     */
    public function it_has_defaults(): void
    {
        $config = $this->process([]);

        self::assertSame(['enabled' => true], $config['javascript']);
        self::assertSame([], $config['query_parameters']);
        self::assertSame(1800, $config['session_timeout']);
    }

    /**
     * @test
     */
    public function it_accepts_a_single_query_parameter_as_a_string(): void
    {
        $config = $this->process([
            'query_parameters' => [
                'pinterest' => ['matches' => 'epik', 'source' => 'pinterest'],
            ],
        ]);

        self::assertSame([
            'pinterest' => ['matches' => ['epik'], 'source' => 'pinterest', 'enabled' => true, 'medium' => null, 'campaign' => null],
        ], $config['query_parameters']);
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<mixed>
     */
    private function process(array $config): array
    {
        return (new Processor())->processConfiguration(new Configuration(), [$config]);
    }
}
