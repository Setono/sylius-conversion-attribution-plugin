<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\Controller\Action;

use PHPUnit\Framework\TestCase;
use Setono\SyliusConversionAttributionPlugin\Controller\Action\TrackPayload;
use Symfony\Component\Validator\Validation;

final class TrackPayloadTest extends TestCase
{
    /**
     * @test
     */
    public function it_accepts_a_page_view(): void
    {
        self::assertCount(0, $this->validate(str_repeat('a', 4096), str_repeat('a', 4096)));
        self::assertCount(0, $this->validate('https://shop.example/', null));
    }

    /**
     * @test
     */
    public function it_requires_a_page(): void
    {
        self::assertCount(1, $this->validate('', null));
    }

    /**
     * @test
     */
    public function it_rejects_over_long_values(): void
    {
        self::assertCount(2, $this->validate(str_repeat('a', 4097), str_repeat('a', 4097)));
    }

    private function validate(string $page, ?string $referrer): iterable
    {
        $payload = new TrackPayload();
        $payload->page = $page;
        $payload->referrer = $referrer;

        return Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()->validate($payload);
    }
}
