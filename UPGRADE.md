# UPGRADE FROM 1.x TO 2.0

The tracking script no longer embeds anything per-visitor, so pages carrying it can be stored in a
full page cache (see [#7](https://github.com/Setono/sylius-conversion-attribution-plugin/issues/7)).
It is now a static file with all per-visitor work done server side when a page view is POSTed.

## Deployment

- The tracking script is a bundle asset now (`src/Resources/public/js/track.js`) instead of an inline
  script. Run `bin/console assets:install` when deploying (Sylius' own deployment already does).
- If you have a Content Security Policy, the inline script needed `'unsafe-inline'` or a hash; the new
  script only needs its origin in `script-src` (and `/track` in `connect-src`). See the README.
- Purge your full page cache after upgrading. Pages cached with the 1.x inline snippet keep POSTing
  their baked-in payload; `/track` still accepts it, but only uses its `page` and `referrer`, and the
  referrer is the one of whichever visitor filled the cache.

## Tracking endpoint

- `POST /track` takes `{"page": "…", "referrer": "…"}` (`Controller\Action\TrackPayload`), and `page`
  is required. The client id, source, medium, campaign, IP and user agent are always resolved server
  side; posted values for them are ignored.
- An over-long value for a string column of the `source` table (e.g. a `utm_campaign` longer than 255
  characters) is answered with 422 and not stored, as before. The limits now live in a validation
  mapping on `Model\Source` (`src/Resources/config/validation/Source.xml`), so a model class you
  override inherits them. User agents are no longer limited to 1024 characters (it's a text column).

## Attribution

An order's attribution is now recorded once, in a new table (`setono_sylius_conversion_attribution__attribution`,
model `Model\Attribution`), instead of being looked up each time the admin order page is shown. It's the
newest non-direct page view tracked for the order's client id, or any of its customer's, in the lookback window
before checkout (`lookback_window`, 30 days by default) or, if there is none, the newest direct one or, failing
that, nothing. Before, it was the newest non-direct one of the order's client id of any age, and an order lost it
when the prune command deleted that page view. The prune command now keeps only the lookback window (30 days
instead of 180), as older page views can't be credited anymore.

A customer's client ids (`Model\CustomerInterface::getClientIds()`, the browsers they checked out from) were
collected but never used. Now they credit a returning customer's page views from their other browsers, including
the client ids collected under 1.x. Implementing `CustomerInterface` on your `Customer` entity is optional: drop it,
and its column, if you don't want a customer's browsers linked, and only the browser an order was checked out from
counts.

Attributions are recorded by a new command, `setono:sylius-conversion-attribution:attribute`, not during the
checkout, so recording one can never make a checkout fail. Schedule it every few minutes; until it has run,
the admin order page says the order hasn't been attributed yet.

**Upgrade in this order**, or the prune command deletes the page views older orders need:

1. Stop the scheduled prune command.
2. Deploy, and add the new table with a migration (`doctrine:migrations:diff` and
   `doctrine:migrations:migrate`).
3. Attribute all the orders completed so far, from the page views still stored (`--all` also takes orders
   completed before the lookback window, which a scheduled run skips):
   ```bash
   php bin/console setono:sylius-conversion-attribution:attribute --all
   ```
4. Schedule the attribute command every few minutes, and the prune command again (e.g. daily).

- If you override the admin template (`order/attribution.html.twig`), call
  `setono_sylius_conversion_attribution(order)` instead of
  `setono_sylius_conversion_attribution_last_non_direct(order)`. It returns a `Model\AttributionInterface`
  (`landingPage` and `trackedAt` instead of `page` and `createdAt`; `hasPageView()` is false for an order
  credited to nothing) or `null` until the order is attributed. Start from the plugin's new template, which
  escapes the source and only links http(s) addresses.
- The translation keys `attribution_body_without_source` and `direct` are replaced by
  `attribution_body_direct`, `attribution_body_without_page_view`, `attribution_body_pending` and
  `attribution_body_not_tracked`.
- The source and attribution resources use the plugin's `Repository\SourceRepository` and
  `Repository\AttributionRepository` by default. If you configure your own repository class for either, extend
  the plugin's (or implement `Repository\SourceRepositoryInterface` or `Repository\AttributionRepositoryInterface`).

## Constructor and signature changes

If you redefine, decorate or extend these services, update the arguments:

- `Controller\Action\TrackAction::__construct(SourceFactoryInterface $sourceFactory, ManagerRegistry $managerRegistry, BotDetectorInterface $botDetector, ClientContextInterface $clientContext, SourceMatcherInterface $sourceMatcher, ValidatorInterface $validator)`.
  All arguments are required. `__invoke()` takes a `TrackPayload` and the (required) `Request`, and
  builds the `Source` itself, with the source `direct` (`Model\SourceInterface::DIRECT`) when no matcher
  matches. The `setono_sylius_conversion_attribution.source.default` parameter is gone.
- `EventSubscriber\AddJavascriptSubscriber::__construct(TagBagInterface $tagBag, UrlGeneratorInterface $urlGenerator, Packages $packages, int $sessionTimeout, string $scriptVersion)`.
  The client information resolver, bot detector, cookie provider and source matcher arguments are gone.
  The subscriber adds a `ScriptTag` instead of an `InlineScriptTag`.
- `SourceFactoryInterface::createFromClientInformation()` is removed; use `createNew()` and the setters.
- `Matcher\ReferrerBasedSourceMatcher::__construct(ReferrerDatabaseInterface $referrerDatabase, string $medium = 'referral')`.
  It takes the referrer database instead of the referrer parser.
- `CacheWarmer\ReferrersCacheWarmer::__construct(ReferrerDatabase $referrerDatabase, string $url = …)`.
  It builds the referrer database instead of writing a cache file itself.
- `Command\PruneCommand::__construct(SourceRepositoryInterface $sourceRepository, int $lookbackWindow)`.
  It keeps the lookback window instead of an optional `$daysToKeep` (180).
- `Twig\Runtime::__construct(AttributionRepositoryInterface $attributionRepository)`, and
  `getLastNonDirect()` is replaced by `getAttribution()`, which returns the order's stored
  `Model\AttributionInterface` (the `setono_sylius_conversion_attribution(order)` Twig function).

## Source matchers

- `Matcher\SourceMatcherInterface::match()` takes a `Matcher\PageView` instead of a `Request`: the
  visited page's `url`, its `referrer` and `host`, and `getQueryParameter()` (string values only, so a
  crafted `?utm_source[]=x` no longer causes a 400). Matching happens when the tracking script reports a
  page view, so there is no meaningful request to match on. Update your custom matchers accordingly.
- `Matcher\PageView` normalizes what the browser reported: `getQueryParameter()` returns values trimmed
  and `null` for a blank one, `referrer` is trimmed and `null` when blank, and the new `referrerHost`
  holds the referrer's host in lower case. Custom matchers no longer need to check for `''`.
- `Matcher\Source` trims its values, turns a blank medium or campaign into `null`, and throws an
  `InvalidArgumentException` for a blank source. A matcher that can't name the source must return `null`.
  A link with a blank `utm_source` (e.g. `?utm_source=&utm_medium=cpc`) is no longer stored with an empty
  source; the referrer decides the source instead, as when `utm_source` is missing.
- Referrers are looked up in `Referrer\ReferrerDatabaseInterface::lookup()`, which returns a
  `Matcher\Source`. Sites Snowplow lists without a medium (`unknown`, e.g. Google Maps, Google Drive and
  Yandex Maps) are now credited to their source with the medium `referral` instead of to their host, and
  hosts match regardless of their case.

## Removed classes

- `ClientInformation\ClientInformation`, `Resolver\ClientInformationResolver` and
  `Resolver\ClientInformationResolverInterface` (and the service of the same name). To change how the
  traffic source is resolved, add a source matcher (tag `setono_sylius_conversion_attribution.source_matcher`);
  the client id comes from `setono/client-bundle`'s `ClientContextInterface`.
- `Parser\Referrer`, `Parser\ReferrerParser` and `Parser\ReferrerParserInterface` (and their services),
  and the `setono_sylius_conversion_attribution.referrers.cache.adapter` cache service. To look referrers
  up differently, point the `Referrer\ReferrerDatabaseInterface` alias at your own implementation.
- `Provider\OrderAttributionProvider` and `Provider\OrderAttributionProviderInterface` (and their service).
  `Attribution\OrderAttributorInterface` decides an order's attribution, which the attribute command stores;
  read it with the `setono_sylius_conversion_attribution(order)` Twig function or
  `Repository\AttributionRepositoryInterface::findOneByOrder()`.

## Dependencies

- `symfony/asset` is required (the script's URL is generated through the `assets.packages` service).
- `symfony/deprecation-contracts` is no longer required.
- `symfony/cache` is no longer required.
- `doctrine/collections` is required.
- `symfony/lock` is required: the attribute command takes a lock through the application's lock factory
  (`framework.lock`), so overlapping runs don't attribute an order twice.
- Twig 2 is no longer supported: `twig/twig` ^3.21 is required.
