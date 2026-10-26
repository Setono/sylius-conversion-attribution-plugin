<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\Controller\Action;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Setono\BotDetectionBundle\BotDetector\BotDetectorInterface;
use Setono\Client\Client;
use Setono\ClientBundle\Context\ClientContextInterface;
use Setono\SyliusConversionAttributionPlugin\Controller\Action\TrackAction;
use Setono\SyliusConversionAttributionPlugin\Controller\Action\TrackPayload;
use Setono\SyliusConversionAttributionPlugin\Factory\SourceFactoryInterface;
use Setono\SyliusConversionAttributionPlugin\Matcher\PageView;
use Setono\SyliusConversionAttributionPlugin\Matcher\Source as MatchedSource;
use Setono\SyliusConversionAttributionPlugin\Matcher\SourceMatcherInterface;
use Setono\SyliusConversionAttributionPlugin\Model\Source;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Validation;

final class TrackActionTest extends TestCase
{
    /**
     * @test
     */
    public function it_persists_a_source_for_the_posted_page_view(): void
    {
        $matcher = $this->createMock(SourceMatcherInterface::class);
        $matcher->expects(self::once())->method('match')->willReturnCallback(
            static function (PageView $pageView): MatchedSource {
                self::assertSame('https://shop.example/p?utm_source=google&utm_medium=cpc&utm_campaign=summer', $pageView->url);
                self::assertSame('https://google.com/', $pageView->referrer);

                return new MatchedSource('google', 'cpc', 'summer');
            },
        );

        $persisted = null;
        $action = $this->createAction($matcher, $this->persistingRegistry($persisted));

        $response = $action(
            $this->payload('https://shop.example/p?utm_source=google&utm_medium=cpc&utm_campaign=summer', 'https://google.com/'),
            $this->trackRequest(),
        );

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        self::assertInstanceOf(Source::class, $persisted);
        self::assertSame('client-from-cookie', $persisted->getClientId());
        self::assertSame('https://shop.example/p?utm_source=google&utm_medium=cpc&utm_campaign=summer', $persisted->getPage());
        self::assertSame('https://google.com/', $persisted->getReferrer());
        self::assertSame('203.0.113.9', $persisted->getIp());
        self::assertSame('RealBrowser', $persisted->getUserAgent());
        self::assertSame('google', $persisted->getSource());
        self::assertSame('cpc', $persisted->getMedium());
        self::assertSame('summer', $persisted->getCampaign());
    }

    /**
     * @test
     */
    public function it_falls_back_to_the_default_source_when_nothing_matches(): void
    {
        $persisted = null;
        $action = $this->createAction($this->matcher(null), $this->persistingRegistry($persisted));

        $action($this->payload('https://shop.example/p'), $this->trackRequest());

        self::assertInstanceOf(Source::class, $persisted);
        self::assertSame('direct', $persisted->getSource());
        self::assertNull($persisted->getMedium());
        self::assertNull($persisted->getCampaign());
    }

    /**
     * @test
     */
    public function it_stores_no_ip_or_user_agent_the_real_request_lacks(): void
    {
        $request = $this->trackRequest();
        $request->server->remove('REMOTE_ADDR');
        $request->headers->remove('user-agent');

        $persisted = null;
        $action = $this->createAction($this->matcher(null), $this->persistingRegistry($persisted));

        $action($this->payload('https://shop.example/p'), $request);

        self::assertInstanceOf(Source::class, $persisted);
        self::assertNull($persisted->getIp());
        self::assertNull($persisted->getUserAgent());
    }

    /**
     * @test
     *
     * @dataProvider emptyReferrers
     */
    public function it_passes_no_referrer_to_the_matchers_when_none_is_posted(?string $referrer): void
    {
        $matcher = $this->createMock(SourceMatcherInterface::class);
        $matcher->expects(self::once())->method('match')->willReturnCallback(
            static function (PageView $pageView): ?MatchedSource {
                self::assertNull($pageView->referrer);

                return null;
            },
        );

        $persisted = null;
        $action = $this->createAction($matcher, $this->persistingRegistry($persisted));

        $action($this->payload('https://shop.example/p', $referrer), $this->trackRequest());

        self::assertInstanceOf(Source::class, $persisted);
        self::assertNull($persisted->getReferrer());
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
    public function it_rejects_values_that_do_not_fit_the_columns(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects(self::never())->method('getManagerForClass');

        // e.g. a utm_campaign longer than the campaign column
        $action = $this->createAction($this->matcher(new MatchedSource('newsletter', 'email', str_repeat('a', 256))), $registry);

        $response = $action($this->payload('https://shop.example/p'), $this->trackRequest());

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function it_ignores_bot_requests_without_matching_or_persisting(): void
    {
        $matcher = $this->createMock(SourceMatcherInterface::class);
        $matcher->expects(self::never())->method('match');

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects(self::never())->method('getManagerForClass');

        $action = $this->createAction($matcher, $registry, isBot: true);

        $response = $action($this->payload('https://shop.example/p'), $this->trackRequest());

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    private function createAction(SourceMatcherInterface $matcher, ManagerRegistry $registry, bool $isBot = false): TrackAction
    {
        $factory = $this->createMock(SourceFactoryInterface::class);
        $factory->method('createNew')->willReturnCallback(static fn (): Source => new Source());

        $botDetector = $this->createMock(BotDetectorInterface::class);
        $botDetector->method('isBotRequest')->willReturn($isBot);

        $clientContext = $this->createMock(ClientContextInterface::class);
        $clientContext->method('getClient')->willReturn(new Client('client-from-cookie'));

        return new TrackAction(
            $factory,
            $registry,
            $botDetector,
            $clientContext,
            $matcher,
            Validation::createValidatorBuilder()
                ->addXmlMapping(__DIR__ . '/../../../src/Resources/config/validation/Source.xml')
                ->getValidator(),
            'direct',
        );
    }

    private function matcher(?MatchedSource $match): SourceMatcherInterface
    {
        $matcher = $this->createMock(SourceMatcherInterface::class);
        $matcher->method('match')->willReturn($match);

        return $matcher;
    }

    private function payload(string $page, ?string $referrer = null): TrackPayload
    {
        $payload = new TrackPayload();
        $payload->page = $page;
        $payload->referrer = $referrer;

        return $payload;
    }

    /**
     * Returns a registry whose manager captures the persisted entity into $persisted
     */
    private function persistingRegistry(?object &$persisted): ManagerRegistry
    {
        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects(self::once())->method('persist')->willReturnCallback(
            static function (object $entity) use (&$persisted): void {
                $persisted = $entity;
            },
        );
        $manager->expects(self::once())->method('flush');

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->with(Source::class)->willReturn($manager);

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
