<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Setono\SyliusConversionAttributionPlugin\DependencyInjection\Configuration;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
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
        self::assertSame([], $config['referrers']);
        self::assertSame(1800, $config['session_timeout']);
        self::assertSame(30, $config['lookback_window']);
    }

    /**
     * @test
     */
    public function it_rejects_a_lookback_window_shorter_than_a_day(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process(['lookback_window' => 0]);
    }

    /**
     * @test
     */
    public function it_accepts_a_lookback_window_of_a_day(): void
    {
        self::assertSame(1, $this->process(['lookback_window' => 1])['lookback_window']);
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
     * @test
     */
    public function it_accepts_referrers(): void
    {
        $config = $this->process([
            'referrers' => [
                'partner' => ['hosts' => 'partner.example', 'source' => 'partner'],
                'x' => ['hosts' => ['t.co', 'x.com'], 'source' => 'x', 'medium' => 'social'],
            ],
        ]);

        self::assertSame([
            'partner' => ['hosts' => ['partner.example'], 'source' => 'partner', 'enabled' => true, 'medium' => null],
            'x' => ['hosts' => ['t.co', 'x.com'], 'source' => 'x', 'medium' => 'social', 'enabled' => true],
        ], $config['referrers']);
    }

    /**
     * @test
     *
     * @dataProvider invalidReferrers
     *
     * @param array<string, mixed> $referrer
     */
    public function it_rejects_an_invalid_referrer(array $referrer): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process(['referrers' => ['partner' => $referrer]]);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidReferrers(): iterable
    {
        yield 'no source' => [['hosts' => ['partner.example']]];
        yield 'a blank source' => [['hosts' => ['partner.example'], 'source' => '']];
        yield 'no hosts' => [['hosts' => [], 'source' => 'partner']];
        yield 'a blank host' => [['hosts' => [''], 'source' => 'partner']];
        yield 'a blank medium' => [['hosts' => ['partner.example'], 'source' => 'partner', 'medium' => '']];
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
