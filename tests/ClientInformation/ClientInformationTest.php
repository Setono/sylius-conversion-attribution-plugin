<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\ClientInformation;

use PHPUnit\Framework\TestCase;
use Setono\SyliusConversionAttributionPlugin\ClientInformation\ClientInformation;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ClientInformationTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    /**
     * @test
     */
    public function it_rejects_an_over_long_value(): void
    {
        // Without this constraint an over-long value reaches the string column and blows up with a
        // 500 on flush; the constraint turns it into a 422 at the MapRequestPayload boundary instead
        $clientInformation = new ClientInformation();
        $clientInformation->clientId = str_repeat('a', 256);

        self::assertGreaterThan(0, $this->validator->validate($clientInformation)->count());
    }

    /**
     * @test
     */
    public function it_accepts_a_value_at_the_limit(): void
    {
        $clientInformation = new ClientInformation();
        $clientInformation->clientId = str_repeat('a', 255);
        $clientInformation->ip = '203.0.113.1';
        $clientInformation->source = 'google';

        self::assertCount(0, $this->validator->validate($clientInformation));
    }

    /**
     * @test
     */
    public function it_filters_empty_values_when_serialized(): void
    {
        $clientInformation = new ClientInformation();
        $clientInformation->source = 'google';

        self::assertSame(['source' => 'google'], $clientInformation->jsonSerialize());
    }
}
