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

- An order is credited to the newest non-direct source tracked **up to its checkout completion**
  (`OrderAttributionProvider`). Before, it was the newest one overall, so a later visit, or the customer
  returning from a payment gateway after checkout, could take the credit for an earlier order.

## Constructor and signature changes

If you redefine, decorate or extend these services, update the arguments:

- `Controller\Action\TrackAction::__construct(SourceFactoryInterface $sourceFactory, ManagerRegistry $managerRegistry, BotDetectorInterface $botDetector, ClientContextInterface $clientContext, SourceMatcherInterface $sourceMatcher, ValidatorInterface $validator, string $defaultSource)`.
  All arguments are required. `__invoke()` takes a `TrackPayload` and the (required) `Request`, and
  builds the `Source` itself.
- `EventSubscriber\AddJavascriptSubscriber::__construct(TagBagInterface $tagBag, UrlGeneratorInterface $urlGenerator, Packages $packages, int $sessionTimeout, string $scriptVersion)`.
  The client information resolver, bot detector, cookie provider and source matcher arguments are gone.
  The subscriber adds a `ScriptTag` instead of an `InlineScriptTag`.
- `SourceFactoryInterface::createFromClientInformation()` is removed; use `createNew()` and the setters.

## Source matchers

- `Matcher\SourceMatcherInterface::match()` takes a `Matcher\PageView` instead of a `Request`: the
  visited page's `url`, its `referrer` and `host`, and `getQueryParameter()` (string values only, so a
  crafted `?utm_source[]=x` no longer causes a 400). Matching happens when the tracking script reports a
  page view, so there is no meaningful request to match on. Update your custom matchers accordingly.

## Removed classes

- `ClientInformation\ClientInformation`, `Resolver\ClientInformationResolver` and
  `Resolver\ClientInformationResolverInterface` (and the service of the same name). To change how the
  traffic source is resolved, add a source matcher (tag `setono_sylius_conversion_attribution.source_matcher`);
  the client id comes from `setono/client-bundle`'s `ClientContextInterface`.

## Dependencies

- `symfony/asset` is required (the script's URL is generated through the `assets.packages` service).
- `symfony/deprecation-contracts` is no longer required.
