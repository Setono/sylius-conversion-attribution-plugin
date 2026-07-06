<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\EventSubscriber;

use PHPUnit\Framework\TestCase;
use Setono\BotDetectionBundle\BotDetector\BotDetectorInterface;
use Setono\Client\Cookie;
use Setono\ClientBundle\CookieProvider\CookieProviderInterface;
use Setono\SyliusConversionAttributionPlugin\ClientInformation\ClientInformation;
use Setono\SyliusConversionAttributionPlugin\EventSubscriber\AddJavascriptSubscriber;
use Setono\SyliusConversionAttributionPlugin\Matcher\Source;
use Setono\SyliusConversionAttributionPlugin\Matcher\SourceMatcherInterface;
use Setono\SyliusConversionAttributionPlugin\Resolver\ClientInformationResolverInterface;
use Setono\TagBag\Tag\InlineScriptTag;
use Setono\TagBag\Tag\TagInterface;
use Setono\TagBag\TagBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class AddJavascriptSubscriberTest extends TestCase
{
    /**
     * @test
     */
    public function it_escapes_client_information_so_it_cannot_break_out_of_the_inline_script(): void
    {
        // Values an attacker can influence via the URL / Referer / User-Agent headers
        $clientInformation = new ClientInformation();
        $clientInformation->source = "src'BREAK";
        $clientInformation->medium = 'med<BREAK';
        $clientInformation->campaign = 'camp&BREAK';
        $clientInformation->page = 'page"BREAK';
        $clientInformation->referrer = 'https://evil/</script>';

        $capturedTag = null;
        $tagBag = $this->createMock(TagBagInterface::class);
        $tagBag->expects(self::once())->method('add')->with(self::callback(
            static function (TagInterface $tag) use (&$capturedTag): bool {
                $capturedTag = $tag;

                return true;
            },
        ));

        $subscriber = $this->createSubscriber(
            tagBag: $tagBag,
            clientInformation: $clientInformation,
            sourceMatcher: $this->matcherReturning(null),
            cookie: null,
        );

        $subscriber->addJavascript($this->createRequestEvent());

        self::assertInstanceOf(InlineScriptTag::class, $capturedTag);
        $content = $capturedTag->getContent();

        // The payload must appear only in its fully hardened form, where ', ", <, > and & are
        // rendered as \uXXXX escapes
        $hardened = json_encode(
            $clientInformation,
            \JSON_THROW_ON_ERROR | \JSON_HEX_TAG | \JSON_HEX_APOS | \JSON_HEX_QUOT | \JSON_HEX_AMP,
        );
        self::assertStringContainsString($hardened, $content);

        // None of the raw break-out sequences may survive into the <script> body
        self::assertStringNotContainsString("src'BREAK", $content);
        self::assertStringNotContainsString('med<BREAK', $content);
        self::assertStringNotContainsString('camp&BREAK', $content);
        self::assertStringNotContainsString('</script>', $content);
    }

    /**
     * @test
     */
    public function it_tracks_a_campaign_click_even_within_the_session_window(): void
    {
        $tagBag = $this->createMock(TagBagInterface::class);
        $tagBag->expects(self::once())->method('add');

        $subscriber = $this->createSubscriber(
            tagBag: $tagBag,
            clientInformation: new ClientInformation(),
            // A campaign marker was matched on this request ...
            sourceMatcher: $this->matcherReturning(new Source('google', 'cpc')),
            // ... even though the visitor was seen a moment ago
            cookie: new Cookie('client-id'),
        );

        $subscriber->addJavascript($this->createRequestEvent());
    }

    /**
     * @test
     */
    public function it_skips_a_non_campaign_request_within_the_session_window(): void
    {
        $tagBag = $this->createMock(TagBagInterface::class);
        $tagBag->expects(self::never())->method('add');

        $subscriber = $this->createSubscriber(
            tagBag: $tagBag,
            clientInformation: new ClientInformation(),
            sourceMatcher: $this->matcherReturning(null),
            cookie: new Cookie('client-id'),
        );

        $subscriber->addJavascript($this->createRequestEvent());
    }

    /**
     * @test
     */
    public function it_does_not_track_bot_requests(): void
    {
        $tagBag = $this->createMock(TagBagInterface::class);
        $tagBag->expects(self::never())->method('add');

        $sourceMatcher = $this->createMock(SourceMatcherInterface::class);
        $sourceMatcher->expects(self::never())->method('match');

        $subscriber = $this->createSubscriber(
            tagBag: $tagBag,
            clientInformation: new ClientInformation(),
            sourceMatcher: $sourceMatcher,
            cookie: null,
            isBot: true,
        );

        $subscriber->addJavascript($this->createRequestEvent());
    }

    /**
     * @test
     *
     * @group legacy
     */
    public function it_triggers_a_deprecation_and_falls_back_when_no_source_matcher_is_injected(): void
    {
        $tagBag = $this->createMock(TagBagInterface::class);
        $tagBag->expects(self::never())->method('add');

        // Legacy wiring: no source matcher. Construction must warn about the deprecation, and a
        // recent cookie must still short-circuit tracking (the missing matcher must not blow up).
        $deprecations = $this->captureDeprecations(function () use ($tagBag): void {
            $subscriber = $this->createSubscriber(
                tagBag: $tagBag,
                clientInformation: new ClientInformation(),
                sourceMatcher: null,
                cookie: new Cookie('client-id'),
            );

            $subscriber->addJavascript($this->createRequestEvent());
        });

        self::assertNotEmpty($deprecations);
        self::assertStringContainsString('$sourceMatcher', $deprecations[0]);
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

    private function createSubscriber(
        TagBagInterface $tagBag,
        ClientInformation $clientInformation,
        ?SourceMatcherInterface $sourceMatcher,
        ?Cookie $cookie,
        bool $isBot = false,
    ): AddJavascriptSubscriber {
        $resolver = $this->createMock(ClientInformationResolverInterface::class);
        $resolver->method('resolve')->willReturn($clientInformation);

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/track');

        $botDetector = $this->createMock(BotDetectorInterface::class);
        $botDetector->method('isBotRequest')->willReturn($isBot);

        $cookieProvider = $this->createMock(CookieProviderInterface::class);
        $cookieProvider->method('getCookie')->willReturn($cookie);

        return new AddJavascriptSubscriber(
            $tagBag,
            $resolver,
            $urlGenerator,
            $botDetector,
            $cookieProvider,
            1800,
            $sourceMatcher,
        );
    }

    private function matcherReturning(?Source $source): SourceMatcherInterface
    {
        $matcher = $this->createMock(SourceMatcherInterface::class);
        $matcher->method('match')->willReturn($source);

        return $matcher;
    }

    private function createRequestEvent(): RequestEvent
    {
        return new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            Request::create('https://shop.example/'),
            HttpKernelInterface::MAIN_REQUEST,
        );
    }
}
