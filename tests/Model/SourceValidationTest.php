<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\Model;

use PHPUnit\Framework\TestCase;
use Setono\SyliusConversionAttributionPlugin\Model\Source;
use Symfony\Component\Validator\Validation;

/**
 * Covers src/Resources/config/validation/Source.xml: without it an over-long value reaches a string
 * column and blows up with a 500 on flush
 */
final class SourceValidationTest extends TestCase
{
    /**
     * @test
     *
     * @dataProvider stringColumns
     */
    public function it_rejects_a_value_longer_than_its_string_column(string $setter): void
    {
        $source = new Source();
        $source->{$setter}(str_repeat('a', 256));

        self::assertCount(1, $this->validate($source));

        $source->{$setter}(str_repeat('a', 255));

        self::assertCount(0, $this->validate($source));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function stringColumns(): iterable
    {
        yield 'client id' => ['setClientId'];
        yield 'ip' => ['setIp'];
        yield 'source' => ['setSource'];
        yield 'medium' => ['setMedium'];
        yield 'campaign' => ['setCampaign'];
    }

    /**
     * @test
     */
    public function it_accepts_long_values_in_text_columns(): void
    {
        $source = new Source();
        $source->setPage(str_repeat('a', 4096));
        $source->setReferrer(str_repeat('a', 4096));
        $source->setUserAgent(str_repeat('a', 4096));

        self::assertCount(0, $this->validate($source));
    }

    private function validate(Source $source): iterable
    {
        return Validation::createValidatorBuilder()
            ->addXmlMapping(__DIR__ . '/../../src/Resources/config/validation/Source.xml')
            ->getValidator()
            ->validate($source);
    }
}
