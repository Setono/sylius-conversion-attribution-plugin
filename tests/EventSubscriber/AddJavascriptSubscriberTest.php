<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\EventSubscriber;

use PHPUnit\Framework\TestCase;
use Setono\SyliusConversionAttributionPlugin\EventSubscriber\AddJavascriptSubscriber;
use Setono\TagBag\Renderer\ElementRenderer;
use Setono\TagBag\Tag\ScriptTag;
use Setono\TagBag\Tag\TagInterface;
use Setono\TagBag\TagBagInterface;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Asset\PathPackage;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;
use Symfony\Component\Asset\VersionStrategy\StaticVersionStrategy;
use Symfony\Component\Asset\VersionStrategy\VersionStrategyInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The injected tag must be identical for every visitor: the rendered HTML may be stored by a full
 * page cache and served to anyone (see issue #7)
 */
final class AddJavascriptSubscriberTest extends TestCase
{
    /**
     * @test
     */
    public function it_adds_a_static_script_tag(): void
    {
        $tags = [];
        $subscriber = $this->createSubscriber($this->capturingTagBag($tags));
        $subscriber->addJavascript($this->createRequestEvent());

        self::assertCount(1, $tags);
        self::assertInstanceOf(ScriptTag::class, $tags[0]);
        self::assertSame(
            '<script src="/bundles/setonosyliusconversionattributionplugin/js/track.js?v=abc123" id="ssca" defer data-endpoint="/track" data-session-timeout="1800"></script>',
            (new ElementRenderer())->render($tags[0]),
        );
    }

    /**
     * @test
     */
    public function it_adds_an_identical_tag_for_different_visitors(): void
    {
        $tags = [];
        $subscriber = $this->createSubscriber($this->capturingTagBag($tags));

        // Two visitors with different identities, campaigns, referrers and user agents
        $subscriber->addJavascript($this->createRequestEvent(Request::create(
            'https://shop.example/?utm_source=google&utm_medium=cpc',
            'GET',
            [],
            ['setono_client_id' => 'client-a'],
            [],
            ['HTTP_REFERER' => 'https://google.com/', 'HTTP_USER_AGENT' => 'Browser A'],
        )));
        $subscriber->addJavascript($this->createRequestEvent(Request::create(
            'https://shop.example/some/other/page',
            'GET',
            [],
            ['setono_client_id' => 'client-b'],
            [],
            ['HTTP_REFERER' => 'https://shop.example/', 'HTTP_USER_AGENT' => 'Browser B'],
        )));

        self::assertCount(2, $tags);
        self::assertEquals($tags[0], $tags[1]);
    }

    /**
     * @test
     */
    public function it_appends_the_script_version_to_an_already_versioned_url(): void
    {
        // The host app's asset version strategy may already have added a query string
        $tags = [];
        $subscriber = $this->createSubscriber($this->capturingTagBag($tags), versionStrategy: new StaticVersionStrategy('v1'));
        $subscriber->addJavascript($this->createRequestEvent());

        self::assertInstanceOf(ScriptTag::class, $tags[0]);
        self::assertSame('/bundles/setonosyliusconversionattributionplugin/js/track.js?v1&amp;v=abc123', $tags[0]->getSrc());
    }

    /**
     * @test
     */
    public function it_escapes_the_attribute_values(): void
    {
        // The tag bag renders attribute values raw, so a hostile base path or route prefix must not
        // be able to break out of the attribute
        $tags = [];
        $subscriber = $this->createSubscriber(
            $this->capturingTagBag($tags),
            trackUrl: '/track"><script>alert(1)</script>',
            basePath: '/shop"onload="x',
        );
        $subscriber->addJavascript($this->createRequestEvent());

        self::assertInstanceOf(ScriptTag::class, $tags[0]);
        self::assertSame(
            '<script src="/shop&quot;onload=&quot;x/bundles/setonosyliusconversionattributionplugin/js/track.js?v=abc123" id="ssca" defer data-endpoint="/track&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;" data-session-timeout="1800"></script>',
            (new ElementRenderer())->render($tags[0]),
        );
    }

    /**
     * @test
     *
     * @dataProvider nonTrackablePageViews
     */
    public function it_does_not_add_the_script_for_non_trackable_page_views(string $case): void
    {
        $tagBag = $this->createMock(TagBagInterface::class);
        $tagBag->expects(self::never())->method('add');

        $subscriber = $this->createSubscriber($tagBag);
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
     * @param list<TagInterface> $tags
     */
    private function capturingTagBag(array &$tags): TagBagInterface
    {
        $tagBag = $this->createMock(TagBagInterface::class);
        $tagBag->method('add')->willReturnCallback(static function (TagInterface $tag) use (&$tags, $tagBag): TagBagInterface {
            $tags[] = $tag;

            return $tagBag;
        });

        return $tagBag;
    }

    private function createSubscriber(
        TagBagInterface $tagBag,
        string $trackUrl = '/track',
        string $basePath = '',
        VersionStrategyInterface $versionStrategy = new EmptyVersionStrategy(),
    ): AddJavascriptSubscriber {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->with('setono_sylius_conversion_attribution_global_track')->willReturn($trackUrl);

        return new AddJavascriptSubscriber(
            $tagBag,
            $urlGenerator,
            new Packages(new PathPackage($basePath, $versionStrategy)),
            1800,
            'abc123',
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
