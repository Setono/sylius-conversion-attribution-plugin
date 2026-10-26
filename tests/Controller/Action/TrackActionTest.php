<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\Controller\Action;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Setono\BotDetectionBundle\BotDetector\BotDetectorInterface;
use Setono\SyliusConversionAttributionPlugin\ClientInformation\ClientInformation;
use Setono\SyliusConversionAttributionPlugin\Controller\Action\TrackAction;
use Setono\SyliusConversionAttributionPlugin\Controller\Action\TrackPayload;
use Setono\SyliusConversionAttributionPlugin\Factory\SourceFactoryInterface;
use Setono\SyliusConversionAttributionPlugin\Model\Source;
use Setono\SyliusConversionAttributionPlugin\Resolver\ClientInformationResolverInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Validation;

final class TrackActionTest extends TestCase
{
    /**
     * @test
     */
    public function it_persists_a_source_resolved_from_the_posted_page_view(): void
    {
        $payload = $this->payload(
            'https://shop.example/p?utm_source=google&utm_medium=cpc&utm_campaign=summer',
            'https://google.com/',
        );

        // The resolver also reports page, referrer, ip and user agent, but from the synthetic
        // request; those must not win
        $resolved = new ClientInformation();
        $resolved->clientId = 'client-from-cookie';
        $resolved->page = 'https://shop.example/p?utm_campaign=summer&utm_medium=cpc&utm_source=google';
        $resolved->referrer = 'resolved-referrer';
        $resolved->ip = '127.0.0.1';
        $resolved->userAgent = 'Symfony';
        $resolved->source = 'google';
        $resolved->medium = 'cpc';
        $resolved->campaign = 'summer';

        $resolver = $this->createMock(ClientInformationResolverInterface::class);
        $resolver->expects(self::once())->method('resolve')->willReturnCallback(
            static function (Request $synthetic) use ($resolved): ClientInformation {
                // The matchers must see the posted page (host, path, query) and the posted referrer
                self::assertSame('shop.example', $synthetic->getHost());
                self::assertSame('/p', $synthetic->getPathInfo());
                self::assertSame('google', $synthetic->query->get('utm_source'));
                self::assertSame('cpc', $synthetic->query->get('utm_medium'));
                self::assertSame('summer', $synthetic->query->get('utm_campaign'));
                self::assertSame('https://google.com/', $synthetic->headers->get('referer'));

                return $resolved;
            },
        );

        $captured = null;
        $action = $this->createAction($resolver, $this->capturingSourceFactory($captured), $this->persistingRegistry());

        $response = $action($payload, $this->trackRequest());

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        self::assertInstanceOf(ClientInformation::class, $captured);
        self::assertSame('client-from-cookie', $captured->clientId);
        self::assertSame('https://shop.example/p?utm_source=google&utm_medium=cpc&utm_campaign=summer', $captured->page);
        self::assertSame('https://google.com/', $captured->referrer);
        self::assertSame('203.0.113.9', $captured->ip);
        self::assertSame('RealBrowser', $captured->userAgent);
        self::assertSame('google', $captured->source);
        self::assertSame('cpc', $captured->medium);
        self::assertSame('summer', $captured->campaign);
    }

    /**
     * @test
     */
    public function it_stores_no_ip_or_user_agent_the_real_request_lacks(): void
    {
        $request = $this->trackRequest();
        $request->server->remove('REMOTE_ADDR');
        $request->headers->remove('user-agent');

        $resolved = new ClientInformation();
        $resolved->ip = '127.0.0.1';
        $resolved->userAgent = 'Symfony';

        $resolver = $this->createMock(ClientInformationResolverInterface::class);
        $resolver->method('resolve')->willReturn($resolved);

        $captured = null;
        $action = $this->createAction($resolver, $this->capturingSourceFactory($captured), $this->persistingRegistry());

        $action($this->payload('https://shop.example/p'), $request);

        self::assertInstanceOf(ClientInformation::class, $captured);
        self::assertNull($captured->ip);
        self::assertNull($captured->userAgent);
    }

    /**
     * @test
     *
     * @dataProvider emptyReferrers
     */
    public function it_omits_the_referer_header_when_no_referrer_is_posted(?string $referrer): void
    {
        $resolver = $this->createMock(ClientInformationResolverInterface::class);
        $resolver->expects(self::once())->method('resolve')->willReturnCallback(
            static function (Request $synthetic): ClientInformation {
                self::assertFalse($synthetic->headers->has('referer'));

                return new ClientInformation();
            },
        );

        $captured = null;
        $action = $this->createAction($resolver, $this->capturingSourceFactory($captured), $this->persistingRegistry());

        $action($this->payload('https://shop.example/p', $referrer), $this->trackRequest());

        self::assertInstanceOf(ClientInformation::class, $captured);
        self::assertNull($captured->referrer);
    }

    /**
     * @return iterable<string, array{?string}>
     */
    public static function emptyReferrers(): iterable
    {
        yield 'missing' => [null];
        yield 'empty' => [''];
    }

    /**
     * @test
     */
    public function it_rejects_resolved_values_that_do_not_fit_the_columns(): void
    {
        // e.g. a utm_campaign longer than the campaign column
        $resolved = new ClientInformation();
        $resolved->campaign = str_repeat('a', 256);

        $resolver = $this->createMock(ClientInformationResolverInterface::class);
        $resolver->method('resolve')->willReturn($resolved);

        $factory = $this->createMock(SourceFactoryInterface::class);
        $factory->expects(self::never())->method('createFromClientInformation');

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects(self::never())->method('getManagerForClass');

        $action = $this->createAction($resolver, $factory, $registry);

        $response = $action($this->payload('https://shop.example/p'), $this->trackRequest());

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function it_ignores_bot_requests_without_resolving_or_persisting(): void
    {
        $resolver = $this->createMock(ClientInformationResolverInterface::class);
        $resolver->expects(self::never())->method('resolve');

        $factory = $this->createMock(SourceFactoryInterface::class);
        $factory->expects(self::never())->method('createFromClientInformation');

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects(self::never())->method('getManagerForClass');

        $action = $this->createAction($resolver, $factory, $registry, isBot: true);

        $response = $action($this->payload('https://shop.example/p'), $this->trackRequest());

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    private function createAction(
        ClientInformationResolverInterface $resolver,
        SourceFactoryInterface $factory,
        ManagerRegistry $registry,
        bool $isBot = false,
    ): TrackAction {
        $botDetector = $this->createMock(BotDetectorInterface::class);
        $botDetector->method('isBotRequest')->willReturn($isBot);

        return new TrackAction(
            $factory,
            $registry,
            $botDetector,
            $resolver,
            Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator(),
        );
    }

    private function payload(string $page, ?string $referrer = null): TrackPayload
    {
        $payload = new TrackPayload();
        $payload->page = $page;
        $payload->referrer = $referrer;

        return $payload;
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
}
