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
- An over-long resolved value (e.g. a `utm_campaign` longer than 255 characters) is answered with 422
  and not stored, as before.

## Constructor and signature changes

If you redefine, decorate or extend these services, update the arguments:

- `Controller\Action\TrackAction::__construct(SourceFactoryInterface $sourceFactory, ManagerRegistry $managerRegistry, BotDetectorInterface $botDetector, ClientInformationResolverInterface $clientInformationResolver, ValidatorInterface $validator)`.
  All arguments are required. `__invoke()` takes a `TrackPayload` and the (required) `Request`.
- `EventSubscriber\AddJavascriptSubscriber::__construct(TagBagInterface $tagBag, UrlGeneratorInterface $urlGenerator, Packages $packages, int $sessionTimeout, string $scriptVersion)`.
  The client information resolver, bot detector, cookie provider and source matcher arguments are gone.
  The subscriber adds a `ScriptTag` instead of an `InlineScriptTag`.
- `Resolver\ClientInformationResolverInterface::resolve(Request $request)`: the request is required;
  there is no fallback to the main request anymore. `ClientInformationResolver` no longer takes a
  `RequestStack`.
- `ClientInformation\ClientInformation` no longer implements `JsonSerializable`.

## Dependencies

- `symfony/asset` is required (the script's URL is generated through the `assets.packages` service).
- `symfony/deprecation-contracts` is no longer required.
