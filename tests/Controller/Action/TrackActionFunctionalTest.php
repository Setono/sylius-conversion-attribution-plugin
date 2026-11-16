<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\Controller\Action;

use Setono\SyliusConversionAttributionPlugin\CacheWarmer\ReferrersCacheWarmer;
use Setono\SyliusConversionAttributionPlugin\Model\SourceInterface;
use Setono\SyliusConversionAttributionPlugin\Repository\SourceRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Tracks page views through the test application, with the default configuration, the source matchers in
 * config/services_test.yaml and the referrer database built from tests/Application/referrers.json (which knows
 * www.google.com and facebook.com)
 */
final class TrackActionFunctionalTest extends WebTestCase
{
    private const USER_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';

    /**
     * @test
     *
     * @dataProvider pageViews
     *
     * @param array{string, ?string, ?string} $expected the source, medium and campaign
     */
    public function it_stores_the_source_of_a_page_view(string $query, ?string $referrer, array $expected): void
    {
        $client = self::createClient(server: ['HTTP_USER_AGENT' => self::USER_AGENT]);

        // The warmer is optional, so booting the kernel doesn't build the referrer database
        $warmer = self::getContainer()->get(ReferrersCacheWarmer::class);
        self::assertInstanceOf(ReferrersCacheWarmer::class, $warmer);
        $warmer->warmUp($client->getKernel()->getCacheDir());

        $page = 'https://shop.example/?' . $query;
        $client->jsonRequest('POST', '/track', ['page' => $page, 'referrer' => $referrer]);
        self::assertResponseStatusCodeSame(204);

        $sourceRepository = self::getContainer()->get(SourceRepositoryInterface::class);
        self::assertInstanceOf(SourceRepositoryInterface::class, $sourceRepository);
        $source = $sourceRepository->findOneBy(['page' => $page]);
        self::assertInstanceOf(SourceInterface::class, $source);

        self::assertSame($expected, [$source->getSource(), $source->getMedium(), $source->getCampaign()]);
    }

    /**
     * @return iterable<string, array{string, ?string, array{string, ?string, ?string}}>
     */
    public static function pageViews(): iterable
    {
        yield 'UTM parameters' => ['utm_source=newsletter&utm_medium=email&utm_campaign=fall', null, ['newsletter', 'email', 'fall']];

        yield 'a click id with UTM parameters' => ['gclid=abc&utm_source=google&utm_medium=cpc&utm_campaign=spring_sale', null, ['google', 'cpc', 'spring_sale']];

        yield 'a click id with only a utm_campaign' => ['gclid=abc&utm_campaign=spring_sale', null, ['google', 'cpc', 'spring_sale']];

        yield 'a facebook click id' => ['fbclid=abc', null, ['facebook', 'social', null]];

        yield 'a facebook ad' => ['fbclid=abc&utm_source=facebook&utm_medium=paid_social&utm_campaign=post', null, ['facebook', 'paid_social', 'post']];

        yield 'a generic source parameter with UTM parameters naming another source' => ['ref=partner&utm_source=newsletter&utm_medium=email&utm_campaign=november', null, ['newsletter', 'email', 'november']];

        yield 'a utm_source from a referrer naming another source' => ['utm_source=newsletter', 'https://facebook.com/', ['newsletter', null, null]];

        yield 'a utm_source from a referrer naming the same source in another case' => ['utm_source=google&utm_campaign=x', 'https://www.google.com/', ['google', 'search', 'x']];

        // The referrer database's "Google" is stored lowercase, like every source and medium
        yield 'a blank utm_source from a referrer' => ['utm_source=&utm_medium=cpc', 'https://www.google.com/', ['google', 'cpc', null]];

        yield 'UTM parameters without a source and without a referrer' => ['utm_medium=email&utm_campaign=black-friday', null, ['(not set)', 'email', 'black-friday']];

        yield 'UTM parameters without a source from a referrer on the shop' => ['utm_medium=email&utm_campaign=black-friday', 'https://shop.example/cart', ['(not set)', 'email', 'black-friday']];

        yield 'only a blank utm_source' => ['utm_source=', null, ['direct', null, null]];

        yield 'nothing' => ['', null, ['direct', null, null]];

        // A shop's own matchers get the UTM parameters applied too, whatever their priority
        yield 'a matcher tagged above the click ids' => ['partner_400=x&utm_campaign=spring_sale', null, ['partner', 'affiliate', 'spring_sale']];

        yield 'a matcher tagged between the click ids and the referrer' => ['partner_200=x&utm_campaign=spring_sale', null, ['partner', 'affiliate', 'spring_sale']];

        yield 'a matcher tagged below the referrer' => ['partner_50=x&utm_campaign=spring_sale', null, ['partner', 'affiliate', 'spring_sale']];
    }
}
