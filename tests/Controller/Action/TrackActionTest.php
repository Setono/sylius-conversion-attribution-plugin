<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\Controller\Action;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Setono\BotDetectionBundle\BotDetector\BotDetectorInterface;
use Setono\SyliusConversionAttributionPlugin\ClientInformation\ClientInformation;
use Setono\SyliusConversionAttributionPlugin\Controller\Action\TrackAction;
use Setono\SyliusConversionAttributionPlugin\Factory\SourceFactoryInterface;
use Setono\SyliusConversionAttributionPlugin\Model\Source;
use Setono\SyliusConversionAttributionPlugin\Resolver\ClientInformationResolverInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class TrackActionTest extends TestCase
{
    /**
     * @test
     */
    public function it_persists_a_source_for_a_regular_request(): void
    {
        $source = new Source();

        $factory = $this->createMock(SourceFactoryInterface::class);
        $factory->method('createFromClientInformation')->willReturn($source);

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects(self::once())->method('persist')->with($source);
        $manager->expects(self::once())->method('flush');

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($manager);

        $action = new TrackAction($factory, $registry, $this->botDetector(false), $this->resolverNeverCalled());

        $response = $action(new ClientInformation());

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function it_resolves_missing_fields_for_a_minimal_payload(): void
    {
        // The cache-safe snippet posts only page and referrer; everything else must be resolved
        // server side from a synthetic request that carries the posted page (host, path, query),
        // the posted referrer, and the real POST request's IP and user agent
        $payload = new ClientInformation();
        $payload->page = 'https://shop.example/p?utm_source=google&utm_medium=cpc&utm_campaign=summer';
        $payload->referrer = 'https://google.com/';

        $resolved = new ClientInformation();
        $resolved->clientId = 'client-from-cookie';
        $resolved->source = 'google';
        $resolved->medium = 'cpc';
        $resolved->campaign = 'summer';

        $resolver = $this->createMock(ClientInformationResolverInterface::class);
        $resolver->expects(self::once())->method('resolve')->willReturnCallback(
            static function (Request $synthetic) use ($resolved): ClientInformation {
                self::assertSame('shop.example', $synthetic->getHost());
                self::assertSame('/p', $synthetic->getPathInfo());
                self::assertSame('google', $synthetic->query->get('utm_source'));
                self::assertSame('cpc', $synthetic->query->get('utm_medium'));
                self::assertSame('summer', $synthetic->query->get('utm_campaign'));
                self::assertSame('https://google.com/', $synthetic->headers->get('referer'));
                self::assertSame('203.0.113.9', $synthetic->getClientIp());
                self::assertSame('RealBrowser', $synthetic->headers->get('user-agent'));

                return $resolved;
            },
        );

        $captured = null;
        $action = new TrackAction(
            $this->capturingSourceFactory($captured),
            $this->persistingRegistry(),
            $this->botDetector(false),
            $resolver,
        );

        $response = $action($payload, $this->trackRequest());

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        self::assertInstanceOf(ClientInformation::class, $captured);
        self::assertSame('client-from-cookie', $captured->clientId);
        // ip and user agent must come from the real POST request, never from the synthetic
        // request's defaults (127.0.0.1, "Symfony")
        self::assertSame('203.0.113.9', $captured->ip);
        self::assertSame('RealBrowser', $captured->userAgent);
        self::assertSame('google', $captured->source);
        self::assertSame('cpc', $captured->medium);
        self::assertSame('summer', $captured->campaign);
        self::assertSame('https://shop.example/p?utm_source=google&utm_medium=cpc&utm_campaign=summer', $captured->page);
        self::assertSame('https://google.com/', $captured->referrer);
    }

    /**
     * @test
     */
    public function it_lets_a_legacy_payload_win_over_resolution(): void
    {
        // Pages cached before 1.2 keep POSTing the full pre-1.2 payload for their TTL; those must
        // behave exactly as before: no server side resolution at all, every posted value persisted
        $payload = new ClientInformation();
        $payload->clientId = 'legacy-client';
        $payload->ip = '198.51.100.1';
        $payload->userAgent = 'LegacyBrowser';
        $payload->page = 'https://shop.example/landing?utm_source=bing';
        $payload->referrer = 'https://bing.com/';
        $payload->source = 'bing';
        $payload->medium = 'cpc';
        $payload->campaign = 'fall';

        $captured = null;
        $action = new TrackAction(
            $this->capturingSourceFactory($captured),
            $this->persistingRegistry(),
            $this->botDetector(false),
            $this->resolverNeverCalled(),
        );

        $action($payload, $this->trackRequest());

        self::assertInstanceOf(ClientInformation::class, $captured);
        self::assertSame('legacy-client', $captured->clientId);
        self::assertSame('198.51.100.1', $captured->ip);
        self::assertSame('LegacyBrowser', $captured->userAgent);
        self::assertSame('https://shop.example/landing?utm_source=bing', $captured->page);
        self::assertSame('https://bing.com/', $captured->referrer);
        self::assertSame('bing', $captured->source);
        self::assertSame('cpc', $captured->medium);
        self::assertSame('fall', $captured->campaign);
    }

    /**
     * @test
     */
    public function it_lets_posted_fields_win_when_only_some_are_missing(): void
    {
        // The overlay must be per-field: posted values always win, resolution only fills gaps
        $payload = new ClientInformation();
        $payload->clientId = 'posted-client';
        $payload->ip = '198.51.100.2';
        $payload->userAgent = 'PostedBrowser';
        $payload->page = 'https://shop.example/p';
        $payload->referrer = 'https://elsewhere.example/';
        $payload->medium = 'posted-medium';
        $payload->campaign = 'posted-campaign';
        // source is deliberately missing, so resolution must run

        $resolved = new ClientInformation();
        $resolved->clientId = 'resolved-client';
        $resolved->ip = 'resolved-ip';
        $resolved->userAgent = 'resolved-ua';
        $resolved->source = 'referral';
        $resolved->medium = 'resolved-medium';
        $resolved->campaign = 'resolved-campaign';

        $resolver = $this->createMock(ClientInformationResolverInterface::class);
        $resolver->expects(self::once())->method('resolve')->willReturn($resolved);

        $captured = null;
        $action = new TrackAction(
            $this->capturingSourceFactory($captured),
            $this->persistingRegistry(),
            $this->botDetector(false),
            $resolver,
        );

        $action($payload, $this->trackRequest());

        self::assertInstanceOf(ClientInformation::class, $captured);
        self::assertSame('posted-client', $captured->clientId);
        self::assertSame('198.51.100.2', $captured->ip);
        self::assertSame('PostedBrowser', $captured->userAgent);
        self::assertSame('referral', $captured->source);
        self::assertSame('posted-medium', $captured->medium);
        self::assertSame('posted-campaign', $captured->campaign);
    }

    /**
     * @test
     */
    public function it_lets_a_posted_source_win_when_the_client_id_is_missing(): void
    {
        $payload = new ClientInformation();
        $payload->page = 'https://shop.example/p';
        $payload->source = 'newsletter';
        // clientId is deliberately missing, so resolution must run

        $resolved = new ClientInformation();
        $resolved->clientId = 'resolved-client';
        $resolved->source = 'resolved-source';

        $resolver = $this->createMock(ClientInformationResolverInterface::class);
        $resolver->expects(self::once())->method('resolve')->willReturn($resolved);

        $captured = null;
        $action = new TrackAction(
            $this->capturingSourceFactory($captured),
            $this->persistingRegistry(),
            $this->botDetector(false),
            $resolver,
        );

        $action($payload, $this->trackRequest());

        self::assertInstanceOf(ClientInformation::class, $captured);
        self::assertSame('resolved-client', $captured->clientId);
        self::assertSame('newsletter', $captured->source);
    }

    /**
     * @test
     */
    public function it_omits_the_referer_header_when_the_posted_referrer_is_empty(): void
    {
        $payload = new ClientInformation();
        $payload->page = 'https://shop.example/p';
        $payload->referrer = '';

        $resolver = $this->createMock(ClientInformationResolverInterface::class);
        $resolver->expects(self::once())->method('resolve')->willReturnCallback(
            static function (Request $synthetic): ClientInformation {
                self::assertNull($synthetic->headers->get('referer'));

                return new ClientInformation();
            },
        );

        $captured = null;
        $action = new TrackAction(
            $this->capturingSourceFactory($captured),
            $this->persistingRegistry(),
            $this->botDetector(false),
            $resolver,
        );

        $response = $action($payload, $this->trackRequest());

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function it_persists_as_is_when_the_page_is_missing(): void
    {
        // Without a posted page there is nothing to match against — no resolution
        $payload = new ClientInformation();
        $payload->referrer = 'https://google.com/';

        $captured = null;
        $action = new TrackAction(
            $this->capturingSourceFactory($captured),
            $this->persistingRegistry(),
            $this->botDetector(false),
            $this->resolverNeverCalled(),
        );

        $action($payload, $this->trackRequest());

        self::assertInstanceOf(ClientInformation::class, $captured);
        self::assertNull($captured->clientId);
        self::assertNull($captured->source);
    }

    /**
     * @test
     */
    public function it_persists_as_is_when_invoked_without_a_request(): void
    {
        // Direct invocation without a request (pre-1.2 call style) must keep working unchanged
        $payload = new ClientInformation();
        $payload->page = 'https://shop.example/p';

        $captured = null;
        $action = new TrackAction(
            $this->capturingSourceFactory($captured),
            $this->persistingRegistry(),
            $this->botDetector(false),
            $this->resolverNeverCalled(),
        );

        $response = $action($payload);

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        self::assertInstanceOf(ClientInformation::class, $captured);
        self::assertNull($captured->clientId);
        self::assertNull($captured->source);
    }

    /**
     * @test
     */
    public function it_persists_the_payload_as_is_when_the_posted_page_is_malformed(): void
    {
        try {
            Request::create('http://');
            self::markTestSkipped('This version of symfony/http-foundation tolerates malformed URIs');
        } catch (\Throwable) {
            // malformed URIs throw on this version — the action must swallow that
        }

        $payload = new ClientInformation();
        $payload->page = 'http://';

        $captured = null;
        $action = new TrackAction(
            $this->capturingSourceFactory($captured),
            $this->persistingRegistry(),
            $this->botDetector(false),
            $this->resolverNeverCalled(),
        );

        $response = $action($payload, $this->trackRequest());

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        self::assertInstanceOf(ClientInformation::class, $captured);
        self::assertNull($captured->source);
    }

    /**
     * @test
     */
    public function it_ignores_bot_requests_without_persisting(): void
    {
        $factory = $this->createMock(SourceFactoryInterface::class);
        $factory->expects(self::never())->method('createFromClientInformation');

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects(self::never())->method('getManagerForClass');

        // A payload that would otherwise qualify for resolution — the bot check must come first
        $payload = new ClientInformation();
        $payload->page = 'https://shop.example/p';

        $action = new TrackAction($factory, $registry, $this->botDetector(true), $this->resolverNeverCalled());

        $response = $action($payload, $this->trackRequest());

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    /**
     * @test
     *
     * @group legacy
     */
    public function it_triggers_a_deprecation_when_no_resolver_is_injected(): void
    {
        $payload = new ClientInformation();
        $payload->page = 'https://shop.example/p';

        $captured = null;
        $factory = $this->capturingSourceFactory($captured);
        $registry = $this->persistingRegistry();
        $botDetector = $this->botDetector(false);

        $response = null;

        // Legacy three-argument construction (no resolver) must still work, warn, and skip resolution
        $deprecations = $this->captureDeprecations(function () use ($factory, $registry, $botDetector, $payload, &$response): void {
            $action = new TrackAction($factory, $registry, $botDetector);
            $response = $action($payload, $this->trackRequest());
        });

        self::assertCount(1, $deprecations);
        self::assertStringContainsString('$clientInformationResolver', $deprecations[0]);
        self::assertInstanceOf(Response::class, $response);
        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        self::assertInstanceOf(ClientInformation::class, $captured);
        self::assertNull($captured->clientId);
        self::assertNull($captured->source);
    }

    /**
     * @test
     *
     * @group legacy
     */
    public function it_persists_and_warns_without_a_bot_detector_for_backwards_compatibility(): void
    {
        $source = new Source();

        $factory = $this->createMock(SourceFactoryInterface::class);
        $factory->method('createFromClientInformation')->willReturn($source);

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects(self::once())->method('persist')->with($source);
        $manager->expects(self::once())->method('flush');

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($manager);

        $response = null;

        // Legacy two-argument construction (no bot detector, no resolver) must still work and warn
        // about both missing arguments
        $deprecations = $this->captureDeprecations(function () use ($factory, $registry, &$response): void {
            $action = new TrackAction($factory, $registry);
            $response = $action(new ClientInformation());
        });

        self::assertCount(2, $deprecations);
        self::assertStringContainsString('$botDetector', $deprecations[0]);
        self::assertStringContainsString('$clientInformationResolver', $deprecations[1]);
        self::assertInstanceOf(Response::class, $response);
        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    private function botDetector(bool $isBot): BotDetectorInterface
    {
        $botDetector = $this->createMock(BotDetectorInterface::class);
        $botDetector->method('isBotRequest')->willReturn($isBot);

        return $botDetector;
    }

    private function resolverNeverCalled(): ClientInformationResolverInterface
    {
        $resolver = $this->createMock(ClientInformationResolverInterface::class);
        $resolver->expects(self::never())->method('resolve');

        return $resolver;
    }

    /**
     * Returns a factory that captures the ClientInformation it is called with into $captured.
     */
    private function capturingSourceFactory(?ClientInformation &$captured): SourceFactoryInterface
    {
        $factory = $this->createMock(SourceFactoryInterface::class);
        $factory->method('createFromClientInformation')->willReturnCallback(
            static function (ClientInformation $clientInformation) use (&$captured): Source {
                $captured = $clientInformation;

                return new Source();
            },
        );

        return $factory;
    }

    private function persistingRegistry(): ManagerRegistry
    {
        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects(self::once())->method('persist')->with(self::isInstanceOf(Source::class));
        $manager->expects(self::once())->method('flush');

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($manager);

        return $registry;
    }

    private function trackRequest(): Request
    {
        return Request::create('https://shop.example/track', 'POST', [], [], [], [
            'REMOTE_ADDR' => '203.0.113.9',
            'HTTP_USER_AGENT' => 'RealBrowser',
        ]);
    }

    /**
     * Runs $callback with a temporary handler that records E_USER_DEPRECATED messages.
     *
     * @return list<string>
     */
    private function captureDeprecations(callable $callback): array
    {
        $deprecations = [];
        set_error_handler(static function (int $errno, string $errstr) use (&$deprecations): bool {
            $deprecations[] = $errstr;

            return true;
        }, \E_USER_DEPRECATED);

        try {
            $callback();
        } finally {
            restore_error_handler();
        }

        return $deprecations;
    }
}
