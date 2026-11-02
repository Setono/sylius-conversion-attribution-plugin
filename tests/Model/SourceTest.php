<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\Model;

use PHPUnit\Framework\TestCase;
use Setono\SyliusConversionAttributionPlugin\Model\Source;

final class SourceTest extends TestCase
{
    /**
     * @test
     */
    public function it_lowercases_source_and_medium(): void
    {
        $source = new Source();
        $source->setSource('Google');
        $source->setMedium('Organic');

        self::assertSame('google', $source->getSource());
        self::assertSame('organic', $source->getMedium());
    }

    /**
     * @test
     */
    public function it_has_no_id_before_it_is_persisted(): void
    {
        // The id is assigned by the database (integer auto-increment), not generated in the constructor
        self::assertNull((new Source())->getId());
    }

    /**
     * @test
     */
    public function it_is_created_now(): void
    {
        $before = new \DateTimeImmutable();
        $createdAt = (new Source())->getCreatedAt();

        self::assertGreaterThanOrEqual($before, $createdAt);
        self::assertLessThanOrEqual(new \DateTimeImmutable(), $createdAt);
    }

    /**
     * @test
     */
    public function it_converts_to_a_string(): void
    {
        $source = new Source();
        $source->setSource('google');
        $source->setMedium('cpc');

        self::assertSame(sprintf('[%s] google (cpc)', $source->getCreatedAt()->format('Y-m-d H:i')), (string) $source);
    }

    /**
     * @test
     */
    public function it_converts_to_a_string_without_a_source_and_medium(): void
    {
        $source = new Source();

        self::assertSame(sprintf('[%s]  ()', $source->getCreatedAt()->format('Y-m-d H:i')), (string) $source);
    }
}
