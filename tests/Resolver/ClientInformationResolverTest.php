<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\Resolver;

use PHPUnit\Framework\TestCase;
use Setono\Client\Client;
use Setono\ClientBundle\Context\ClientContextInterface;
use Setono\SyliusConversionAttributionPlugin\Matcher\Source;
use Setono\SyliusConversionAttributionPlugin\Matcher\SourceMatcherInterface;
use Setono\SyliusConversionAttributionPlugin\Resolver\ClientInformationResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class ClientInformationResolverTest extends TestCase
{
    /**
     * @test
     */
    public function it_resolves_from_the_given_request(): void
    {
        $resolver = $this->createResolver(new Source('google', 'cpc', 'summer'));

        $clientInformation = $resolver->resolve(Request::create(
            'https://shop.example/landing?utm_source=google',
            'GET',
            [],
            [],
            [],
            [
                'REMOTE_ADDR' => '198.51.100.7',
                'HTTP_USER_AGENT' => 'SomeBrowser',
                'HTTP_REFERER' => 'https://google.com/',
            ],
        ));

        self::assertSame('client-abc', $clientInformation->clientId);
        self::assertSame('https://shop.example/landing?utm_source=google', $clientInformation->page);
        self::assertSame('198.51.100.7', $clientInformation->ip);
        self::assertSame('SomeBrowser', $clientInformation->userAgent);
        self::assertSame('https://google.com/', $clientInformation->referrer);
        self::assertSame('google', $clientInformation->source);
        self::assertSame('cpc', $clientInformation->medium);
        self::assertSame('summer', $clientInformation->campaign);
    }

    /**
     * @test
     */
    public function it_falls_back_to_the_default_source_when_nothing_matches(): void
    {
        $resolver = $this->createResolver(null);

        $clientInformation = $resolver->resolve(Request::create('https://shop.example/'));

        self::assertSame('direct', $clientInformation->source);
        self::assertNull($clientInformation->medium);
        self::assertNull($clientInformation->campaign);
    }

    /**
     * @test
     */
    public function it_uses_the_main_request_when_no_request_is_given(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('https://shop.example/some-page'));

        $resolver = $this->createResolver(null, $requestStack);

        $clientInformation = $resolver->resolve();

        self::assertSame('https://shop.example/some-page', $clientInformation->page);
        self::assertSame('client-abc', $clientInformation->clientId);
    }

    /**
     * @test
     */
    public function it_resolves_empty_client_information_when_no_request_is_available(): void
    {
        $resolver = $this->createResolver(null);

        $clientInformation = $resolver->resolve();

        self::assertNull($clientInformation->clientId);
        self::assertNull($clientInformation->page);
        self::assertNull($clientInformation->ip);
        self::assertNull($clientInformation->userAgent);
        self::assertNull($clientInformation->referrer);
        self::assertNull($clientInformation->source);
        self::assertNull($clientInformation->medium);
        self::assertNull($clientInformation->campaign);
    }

    private function createResolver(?Source $matchedSource, ?RequestStack $requestStack = null): ClientInformationResolver
    {
        $clientContext = $this->createMock(ClientContextInterface::class);
        $clientContext->method('getClient')->willReturn(new Client('client-abc'));

        $sourceMatcher = $this->createMock(SourceMatcherInterface::class);
        $sourceMatcher->method('match')->willReturn($matchedSource);

        return new ClientInformationResolver(
            $requestStack ?? new RequestStack(),
            $clientContext,
            $sourceMatcher,
        );
    }
}
