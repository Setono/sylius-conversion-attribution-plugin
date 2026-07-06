# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A Sylius plugin (`setono/sylius-conversion-attribution-plugin`) that attributes order conversions to traffic sources (google/facebook ads, referrals, direct, etc.). It is a Symfony bundle, not an application — the runnable Sylius app used for tests lives in `tests/Application`. Requires PHP >= 8.1 (local dev pins 8.1 via `.php-version`), Symfony 6.4/7.0, Sylius 1.x.

## Commands

```bash
composer analyse           # Psalm static analysis (config: psalm.xml)
composer check-style       # ECS check (config: ecs.php)
composer fix-style         # ECS auto-fix
composer phpunit           # Run all tests
vendor/bin/phpunit tests/Matcher/QueryParameterBasedSourceMatcherTest.php   # Single test file
vendor/bin/phpunit --filter it_matches                                      # Single test method
vendor/bin/rector process --dry-run   # Rector (CI runs this, non-blocking)
vendor/bin/infection                  # Mutation testing (minCoveredMsi: 100)
vendor/bin/composer-dependency-analyser  # Dependency analysis (CI-enforced)
composer normalize --dry-run          # composer.json normalization (CI-enforced)
```

The test app in `tests/Application` is used by CI for linting and schema validation (needs MySQL via `DATABASE_URL`, `APP_ENV=test`):

```bash
(cd tests/Application && bin/console lint:container)
(cd tests/Application && bin/console lint:yaml ../../src/Resources)
(cd tests/Application && bin/console lint:twig ../../src/Resources)
(cd tests/Application && bin/console doctrine:schema:validate -vvv)
```

Note: `phpunit.xml.dist` bootstraps `tests/Application/config/bootstrap.php`, and `tests/Parser/ReferrerParserFunctionalTest.php` exercises the real referrer database, so tests need `composer install` to have run and network access for that test.

## Architecture

The plugin tracks *where a visitor came from* and later ties that to the order they place. The flow has two halves:

**Capture (visitor side):**
1. `EventSubscriber\AddJavascriptSubscriber` (only loaded when `javascript.enabled` config is true, and requires `setono/tag-bag-bundle` — the extension throws a LogicException if it is missing) injects a JS `fetch()` POST to `/track` on GET HTML requests. It skips bots (`setono/bot-detection-bundle`) and skips visitors seen within `session_timeout` (default 1800s), using the cookie from `setono/client-bundle`.
2. `Controller\Action\TrackAction` (route `setono_sylius_conversion_attribution_global_track`) maps the JSON payload to `ClientInformation\ClientInformation` via `#[MapRequestPayload]` and persists a `Model\Source` row (client id, page, referrer, source/medium/campaign).
3. `Resolver\ClientInformationResolver` builds that payload server-side at injection time: client id from client-bundle's `ClientContext`, plus source/medium/campaign from the matcher chain (falls back to source `direct`).

**Source matching chain:** `Matcher\CompositeSourceMatcher` is populated via `CompositeCompilerPass` from services tagged `setono_sylius_conversion_attribution.source_matcher` (see `SetonoSyliusConversionAttributionPlugin::build()`). First non-null match wins. Built-in matchers, in priority order (priorities set in `services/matcher.xml`):
- `UtmQueryParameterBasedSourceMatcher` — utm_source/utm_medium/utm_campaign
- `QueryParameterBasedSourceMatcher` — click-id params (fbclid, gclid, msclkid, ttclid, twclid, …). The mapping comes from the `query_parameters` config; defaults are prepended in `SetonoSyliusConversionAttributionExtension::prepend()`
- `ReferrerBasedSourceMatcher` — parses the Referer header via `Parser\ReferrerParser`, which looks up hosts in Snowplow's referer database. `CacheWarmer\ReferrersCacheWarmer` downloads that database from S3 at cache warmup into a `PhpArrayAdapter` file (`%kernel.cache_dir%/referrers.php`). Same-host referrers are ignored; unknown cross-host referrers become source=host, medium=referral.

Note the distinction between the two `Source` classes: `Matcher\Source` is a value object (match result); `Model\Source` is the Doctrine entity (a Sylius resource, `setono_sylius_conversion_attribution.source`, mapped via `src/Resources/config/doctrine/model/Source.orm.xml`, integer auto-increment ids).

**Attribution (order side):**
1. On `sylius.order.pre_complete`, `EventSubscriber\AddClientIdSubscriber` stamps the current client id on the order and customer. Host applications must add `OrderInterface`/`OrderTrait` and `CustomerInterface`/`CustomerTrait` (from `src/Model/`) to their Order/Customer entities — see README.
2. `Provider\OrderAttributionProvider` finds the newest non-`direct` Source row for the order's client id.
3. The admin order page shows this via a `sylius_ui` block (`sylius.admin.order.show.sidebar`, prepended in the extension) rendering `src/Resources/views/order/attribution.html.twig` through the Twig extension/runtime in `src/Twig/`.

**Maintenance:** `Command\PruneCommand` (`setono:sylius-conversion-attribution:prune`) deletes Source rows older than 180 days.

## Conventions

- Service definitions are XML, split per concern in `src/Resources/config/services/*.xml` and aggregated by `services.xml`. Services only loaded under config conditions live in `services/conditional/`.
- Interfaces sit next to implementations (`FooInterface` + `Foo`); services are `final` and constructor-injected; Doctrine access goes through `setono/doctrine-orm-trait`'s `ORMTrait` with the resource class name passed as a string parameter (so host apps can override the model class).
- CI (`.github/workflows/build.yaml`) tests PHP 8.1/8.2 with lowest and highest dependencies against Symfony ~6.4.0 — keep code compatible with both dependency extremes and PHP 8.1 syntax.

### Backwards compatibility: adding a constructor argument

This is a published library: adding a required constructor argument to a service (or reordering existing ones) breaks host apps that have redefined the service, decorated it, or instantiate it directly. Follow Symfony's own deprecation layer instead (e.g. `Symfony\Bridge\Doctrine\DataCollector\DoctrineDataCollector::__construct`):

1. Append the new argument **last**, typed **nullable with a `null` default** — never insert it in the middle.
2. In the constructor body, when the argument is `null`, call `trigger_deprecation()` (from `symfony/deprecation-contracts`, a `require` dependency) telling integrators to start passing it. The **version** argument is the release that introduced the deprecation; the message states which major will require it:
   ```php
   if (null === $sourceMatcher) {
       trigger_deprecation(
           'setono/sylius-conversion-attribution-plugin',
           '1.1', // version the arg became "should be passed"
           'Not passing an instance of "%s" as argument "$sourceMatcher" to "%s()" is deprecated and will be required in 2.0.',
           SourceMatcherInterface::class,
           __METHOD__,
       );
   }
   ```
3. Guard every use of the argument against `null` so the old behaviour still works when it is absent.
4. Still inject it in the plugin's own XML service definition (also appended last) so normal installs get full functionality — the nullability only cushions downstream overrides.
5. Add a `@deprecated will be required in 2.0` comment on the property so it's greppable alongside the `trigger_deprecation()` call.

**Removing the shim in the next major:** grep the `src/` tree for `trigger_deprecation` — each hit is a nullable argument to make required (drop the `?`/`= null`, delete the `trigger_deprecation` block and the null-guards). Current shims: `EventSubscriber\AddJavascriptSubscriber` (`$sourceMatcher`) and `Controller\Action\TrackAction` (`$botDetector`).

Test the legacy path by constructing the service without the new argument and asserting the deprecation fires (see `it_triggers_a_deprecation_*` tests, which register a temporary `E_USER_DEPRECATED` handler). Mark such tests `@group legacy`.
