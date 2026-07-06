<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\EventSubscriber;

use PHPUnit\Framework\TestCase;
use Setono\BotDetectionBundle\BotDetector\BotDetectorInterface;
use Setono\ClientBundle\CookieProvider\CookieProviderInterface;
use Setono\SyliusConversionAttributionPlugin\EventSubscriber\AddJavascriptSubscriber;
use Setono\SyliusConversionAttributionPlugin\Matcher\SourceMatcherInterface;
use Setono\SyliusConversionAttributionPlugin\Resolver\ClientInformationResolverInterface;
use Setono\TagBag\Tag\InlineScriptTag;
use Setono\TagBag\Tag\TagInterface;
use Setono\TagBag\TagBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The injected snippet must be byte-identical for every visitor: the rendered HTML may be stored
 * by a full page cache and served to anyone (see issue #7). These tests therefore assert not only
 * what the snippet contains, but also that nothing per-visitor is consulted at render time — the
 * helper wires every per-visitor service with an "expects never" mock.
 */
final class AddJavascriptSubscriberTest extends TestCase
{
    /**
     * @test
     */
    public function it_adds_a_static_snippet(): void
    {
        $capturedTag = null;
        $tagBag = $this->createMock(TagBagInterface::class);
        $tagBag->expects(self::once())->method('add')->with(self::callback(
            static function (TagInterface $tag) use (&$capturedTag): bool {
                $capturedTag = $tag;

                return true;
            },
        ));

        $subscriber = $this->createSubscriber(tagBag: $tagBag);
        $subscriber->addJavascript($this->createRequestEvent());

        self::assertInstanceOf(InlineScriptTag::class, $capturedTag);
        $content = $capturedTag->getContent();

        self::assertStringStartsWith('(function () {', $content);
        // The track URL is JSON-encoded (json_encode escapes the slash by default)
        self::assertStringContainsString('fetch("\/track"', $content);
        // The configured session timeout (1800 s) must be interpolated as milliseconds
        self::assertStringContainsString('1800000', $content);
        self::assertStringContainsString('setono_conversion_attribution_last_seen', $content);
    }

    /**
     * @test
     */
    public function it_adds_an_identical_snippet_for_different_visitors(): void
    {
        $contents = [];
        $tagBag = $this->createMock(TagBagInterface::class);
        $tagBag->expects(self::exactly(2))->method('add')->with(self::callback(
            static function (TagInterface $tag) use (&$contents): bool {
                self::assertInstanceOf(InlineScriptTag::class, $tag);
                $contents[] = $tag->getContent();

                return true;
            },
        ));

        $subscriber = $this->createSubscriber(tagBag: $tagBag);

        // Two visitors with different identities, campaigns, referrers and user agents
        $subscriber->addJavascript($this->createRequestEvent(Request::create(
            'https://shop.example/?utm_source=google&utm_medium=cpc',
            'GET',
            [],
            ['setono_client_id' => '2.1000.2000.client-a'],
            [],
            ['HTTP_REFERER' => 'https://google.com/', 'HTTP_USER_AGENT' => 'Browser A'],
        )));
        $subscriber->addJavascript($this->createRequestEvent(Request::create(
            'https://shop.example/some/other/page',
            'GET',
            [],
            ['setono_client_id' => '2.3000.4000.client-b'],
            [],
            ['HTTP_REFERER' => 'https://shop.example/', 'HTTP_USER_AGENT' => 'Browser B'],
        )));

        self::assertCount(2, $contents);

        // The regression guarded against here: any per-visitor value baked into the snippet would
        // be frozen into a shared page cache and served to every other visitor
        self::assertSame($contents[0], $contents[1]);
        self::assertStringNotContainsString('client-a', $contents[0]);
        self::assertStringNotContainsString('client-b', $contents[0]);
        self::assertStringNotContainsString('utm_source', $contents[0]);
        self::assertStringNotContainsString('google', $contents[0]);
    }

    /**
     * @test
     */
    public function it_adds_the_snippet_even_when_the_visitor_was_seen_recently(): void
    {
        // The session throttle is evaluated client side (localStorage): the cookie must not be
        // consulted at render time (the helper asserts getCookie is never called) and the snippet
        // must be injected regardless of session freshness
        $tagBag = $this->createMock(TagBagInterface::class);
        $tagBag->expects(self::once())->method('add');

        $subscriber = $this->createSubscriber(tagBag: $tagBag);
        $subscriber->addJavascript($this->createRequestEvent());
    }

    /**
     * @test
     */
    public function it_adds_the_snippet_even_for_bot_requests(): void
    {
        // Bot filtering happens in TrackAction at POST time: a render-time bot check would let a
        // bot populate the page cache with a snippet-less page for every human after it (the
        // helper asserts isBotRequest is never called)
        $tagBag = $this->createMock(TagBagInterface::class);
        $tagBag->expects(self::once())->method('add');

        $subscriber = $this->createSubscriber(tagBag: $tagBag);
        $subscriber->addJavascript($this->createRequestEvent());
    }

    /**
     * @test
     *
     * @dataProvider nonTrackablePageViews
     */
    public function it_does_not_add_the_snippet_for_non_trackable_page_views(string $case): void
    {
        $tagBag = $this->createMock(TagBagInterface::class);
        $tagBag->expects(self::never())->method('add');

        $subscriber = $this->createSubscriber(tagBag: $tagBag);
        $subscriber->addJavascript($this->createNonTrackableRequestEvent($case));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function nonTrackablePageViews(): iterable
    {
        yield 'sub request' => ['sub_request'];
        yield 'xhr request' => ['xhr'];
        yield 'post request' => ['post'];
        yield 'non-html request' => ['non_html'];
    }

    /**
     * @test
     */
    public function it_escapes_the_track_url_so_it_cannot_break_out_of_the_inline_script(): void
    {
        $capturedTag = null;
        $tagBag = $this->createMock(TagBagInterface::class);
        $tagBag->expects(self::once())->method('add')->with(self::callback(
            static function (TagInterface $tag) use (&$capturedTag): bool {
                $capturedTag = $tag;

                return true;
            },
        ));

        // A hostile route prefix must not be able to break out of the JS string or the script tag
        $subscriber = $this->createSubscriber(tagBag: $tagBag, trackUrl: "/track'</script>&<x>");
        $subscriber->addJavascript($this->createRequestEvent());

        self::assertInstanceOf(InlineScriptTag::class, $capturedTag);
        $content = $capturedTag->getContent();

        // The URL must appear only in its fully hardened form, where ', ", <, > and & are
        // rendered as \uXXXX escapes
        $hardened = json_encode(
            "/track'</script>&<x>",
            \JSON_THROW_ON_ERROR | \JSON_HEX_TAG | \JSON_HEX_APOS | \JSON_HEX_QUOT | \JSON_HEX_AMP,
        );
        self::assertStringContainsString($hardened, $content);
        self::assertStringNotContainsString('</script>', $content);
        self::assertStringNotContainsString("'</script>", $content);
    }

    /**
     * @test
     */
    public function it_adds_nothing_when_the_track_url_cannot_be_encoded(): void
    {
        $tagBag = $this->createMock(TagBagInterface::class);
        $tagBag->expects(self::never())->method('add');

        // Invalid UTF-8 makes json_encode throw; the subscriber must swallow it and add no tag
        $subscriber = $this->createSubscriber(tagBag: $tagBag, trackUrl: "/track\xB1");
        $subscriber->addJavascript($this->createRequestEvent());
    }

    /**
     * @test
     */
    public function it_does_not_trigger_a_deprecation_when_no_source_matcher_is_injected(): void
    {
        $tagBag = $this->createMock(TagBagInterface::class);
        $tagBag->expects(self::once())->method('add');

        // The source matcher argument is unused since 1.2 (matching happens in TrackAction at
        // POST time), so constructing without it must neither warn nor change behaviour
        $deprecations = $this->captureDeprecations(function () use ($tagBag): void {
            $subscriber = $this->createSubscriber(tagBag: $tagBag, withSourceMatcher: false);
            $subscriber->addJavascript($this->createRequestEvent());
        });

        self::assertSame([], $deprecations);
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
        string $trackUrl = '/track',
        bool $withSourceMatcher = true,
    ): AddJavascriptSubscriber {
        // Everything per-visitor must be off-limits at render time: the resolver, the bot
        // detector, the cookie provider and the source matcher may never be consulted
        $resolver = $this->createMock(ClientInformationResolverInterface::class);
        $resolver->expects(self::never())->method('resolve');

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn($trackUrl);

        $botDetector = $this->createMock(BotDetectorInterface::class);
        $botDetector->expects(self::never())->method('isBotRequest');

        $cookieProvider = $this->createMock(CookieProviderInterface::class);
        $cookieProvider->expects(self::never())->method('getCookie');

        $sourceMatcher = null;
        if ($withSourceMatcher) {
            $sourceMatcher = $this->createMock(SourceMatcherInterface::class);
            $sourceMatcher->expects(self::never())->method('match');
        }

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

    private function createRequestEvent(?Request $request = null): RequestEvent
    {
        return new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $request ?? Request::create('https://shop.example/'),
            HttpKernelInterface::MAIN_REQUEST,
        );
    }

    private function createNonTrackableRequestEvent(string $case): RequestEvent
    {
        $request = Request::create('https://shop.example/', 'post' === $case ? 'POST' : 'GET');
        $type = HttpKernelInterface::MAIN_REQUEST;

        switch ($case) {
            case 'sub_request':
                $type = HttpKernelInterface::SUB_REQUEST;

                break;
            case 'xhr':
                $request->headers->set('X-Requested-With', 'XMLHttpRequest');

                break;
            case 'non_html':
                $request->setRequestFormat('json');

                break;
        }

        return new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            $type,
        );
    }
}
