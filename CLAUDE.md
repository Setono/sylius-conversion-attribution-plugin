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
(cd tests/Application && yarn install && yarn test)  # Jest tests for the tracking script (tests/js, CI-enforced)
(cd tests/Application && yarn build:tracking-script)  # Minify the tracking script after editing it (CI checks the committed result is up to date)
```

The test app in `tests/Application` is used by CI for linting and schema validation (needs MySQL via `DATABASE_URL`, `APP_ENV=test`):

```bash
(cd tests/Application && bin/console lint:container)
(cd tests/Application && bin/console lint:yaml ../../src/Resources)
(cd tests/Application && bin/console lint:twig ../../src/Resources)
(cd tests/Application && bin/console doctrine:schema:validate -vvv)
```

End-to-end tests (`tests/e2e`, Playwright; config in `tests/Application/playwright.config.js`, CI job "End-to-end tests") drive the test application in a real browser: the tracking tag and script, `POST /track` storing sources for the visitor's cookie, the session throttle, bots, and a guest checkout credited in the admin order page. They run the app in the **prod** environment (the test environment's mock session storage sends no cookies) behind PHP's built-in server (`tests/e2e/support/router.php`, port `E2E_PORT`, default 8089). The global setup (`tests/e2e/support/global-setup.js`) drops and recreates the database named by `E2E_DATABASE_URL`, never the ambient `DATABASE_URL`. The variable is required and the database name must end in `_e2e`, both enforced in `tests/e2e/support/app.js`, so a run can't wipe another database. It then loads Sylius' default fixtures, installs assets and rebuilds the prod cache; `E2E_SKIP_SETUP=1` skips it. `PHP_BINARY` picks the PHP binary (default `php`). Locally, with a throwaway MariaDB:

```bash
docker run -d --name sylius-conversion-attribution-e2e-db -p 3308:3306 -e MARIADB_ALLOW_EMPTY_ROOT_PASSWORD=1 mariadb:11.6
(cd tests/Application && yarn install && yarn build && npx playwright install chromium)
(cd tests/Application && E2E_DATABASE_URL='mysql://root@127.0.0.1:3308/setono_sylius_conversion_attribution_e2e?serverVersion=11.6.2-MariaDB' PHP_BINARY="$(brew --prefix php@8.1)/bin/php" yarn e2e)
```

The tests run as a regular browser: `tests/e2e/support/test.js` overrides Playwright's `navigator.webdriver` (opt in with `test.use({webdriver: true})`), and the config sets a desktop Chrome user agent, since both the script and the server side bot detection skip automation. `state()` reads what was stored for the visitor through `tests/e2e/support/state.php`.

Note: `phpunit.xml.dist` bootstraps `tests/Application/config/bootstrap.php`, and `tests/Parser/ReferrerParserFunctionalTest.php` exercises the real referrer database, so tests need `composer install` to have run and network access for that test.

## Architecture

The plugin tracks *where a visitor came from* and later ties that to the order they place. The flow has two halves:

**Capture (visitor side):**
1. `EventSubscriber\AddJavascriptSubscriber` (only loaded when `javascript.enabled` config is true, and requires `setono/tag-bag-bundle` — the extension throws a LogicException if it is missing) adds a **static** `<script src id defer data-endpoint data-session-timeout>` tag for `src/Resources/public/js/track.js` (installed by `assets:install`; URL via `assets.packages`, cache-busted with a content hash computed in the extension) on main-request GET HTML responses. The tag is identical for every visitor so full page caches can safely store it (issue #7) — nothing per-visitor may influence the tag or the injection decision.
2. The tracking script's source is `src/Resources/private/js/track.js` (plain ES5-style JS); `yarn build:tracking-script` minifies it with Terser (pinned to an exact version in `tests/Application/package.json`, so the output is reproducible) into the committed `src/Resources/public/js/track.js`. Edit the source, never the built file: CI rebuilds it and fails on a difference, and Jest (`tests/js`) runs every test against both files. The script finds its tag via `document.currentScript`, falling back to the tag's id for script optimizers like Rocket Loader, skips automated browsers (`navigator.webdriver`, `window.Cypress`), waits for prerendered pages to be shown, throttles itself via a rolling `localStorage` last-seen timestamp (`session_timeout`, default 1800s) so only the first page view of a session is tracked (no bypass for campaign parameters or cross-host referrers: within a session the latter is mostly a customer returning from a payment gateway), and POSTs only `{page, referrer}` to `/track`.
3. `Controller\Action\TrackAction` (route `setono_sylius_conversion_attribution_global_track`) maps the JSON payload to `Controller\Action\TrackPayload` via `#[MapRequestPayload]`, drops bot traffic (`setono/bot-detection-bundle`) and builds the `Model\Source` itself: client id from client-bundle's `ClientContext` (which reads the `setono_client_id` cookie riding on the POST request), page/referrer from the payload, IP/UA from the real request, and source/medium/campaign from the matcher chain, given a `Matcher\PageView` (the posted page URL and referrer; falls back to source `direct`). It validates the entity against `src/Resources/config/validation/Source.xml` (lengths of the string columns; 422 if too long) before persisting.

**Source matching chain:** Matchers implement `Matcher\SourceMatcherInterface::match(PageView $pageView)`. `Matcher\CompositeSourceMatcher` is populated via `CompositeCompilerPass` from services tagged `setono_sylius_conversion_attribution.source_matcher` (see `SetonoSyliusConversionAttributionPlugin::build()`). First non-null match wins. Built-in matchers, in priority order (priorities set in `services/matcher.xml`):
- `UtmQueryParameterBasedSourceMatcher` — utm_source/utm_medium/utm_campaign
- `QueryParameterBasedSourceMatcher` — click-id params (fbclid, gclid, msclkid, ttclid, twclid, …). The mapping comes from the `query_parameters` config; defaults are prepended in `SetonoSyliusConversionAttributionExtension::prepend()`
- `ReferrerBasedSourceMatcher` — parses the page view's referrer via `Parser\ReferrerParser`, which looks up hosts in Snowplow's referer database. `CacheWarmer\ReferrersCacheWarmer` downloads that database from S3 at cache warmup into a `PhpArrayAdapter` file (`%kernel.cache_dir%/referrers.php`). Same-host referrers are ignored; unknown cross-host referrers become source=host, medium=referral.

Note the distinction between the two `Source` classes: `Matcher\Source` is a value object (match result); `Model\Source` is the Doctrine entity (a Sylius resource, `setono_sylius_conversion_attribution.source`, mapped via `src/Resources/config/doctrine/model/Source.orm.xml`, integer auto-increment ids).

**Attribution (order side):**
1. On `sylius.order.pre_complete`, `EventSubscriber\AddClientIdSubscriber` stamps the current client id on the order and customer. Host applications must add `OrderInterface`/`OrderTrait` and `CustomerInterface`/`CustomerTrait` (from `src/Model/`) to their Order/Customer entities — see README.
2. `Provider\OrderAttributionProvider` finds the newest non-`direct` Source row for the order's client id that was tracked up to the order's `checkoutCompletedAt`, so visits after checkout (e.g. returning from a payment gateway) never take the credit.
3. The admin order page shows this via a `sylius_ui` block (`sylius.admin.order.show.sidebar`, prepended in the extension) rendering `src/Resources/views/order/attribution.html.twig` through the Twig extension/runtime in `src/Twig/`.

**Maintenance:** `Command\PruneCommand` (`setono:sylius-conversion-attribution:prune`) deletes Source rows older than 180 days.

## Conventions

- Service definitions are XML, split per concern in `src/Resources/config/services/*.xml` and aggregated by `services.xml`. Services only loaded under config conditions live in `services/conditional/`.
- Interfaces sit next to implementations (`FooInterface` + `Foo`); services are `final` and constructor-injected; Doctrine access goes through `setono/doctrine-orm-trait`'s `ORMTrait` with the resource class name passed as a string parameter (so host apps can override the model class).
- CI (`.github/workflows/build.yaml`) tests PHP 8.1/8.2 with lowest and highest dependencies against Symfony ~6.4.0 — keep code compatible with both dependency extremes and PHP 8.1 syntax. It also runs the Jest suite on Node 22.

### Backwards compatibility: adding a constructor argument

This is a published library: adding a required constructor argument to a service (or reordering existing ones) breaks host apps that have redefined the service, decorated it, or instantiate it directly. Follow Symfony's own deprecation layer instead (e.g. `Symfony\Bridge\Doctrine\DataCollector\DoctrineDataCollector::__construct`):

1. Append the new argument **last**, typed **nullable with a `null` default** — never insert it in the middle.
2. In the constructor body, when the argument is `null`, call `trigger_deprecation()` (from `symfony/deprecation-contracts`; add it to `require` with the first shim, 2.0 dropped it) telling integrators to start passing it. The **version** argument is the release that introduced the deprecation; the message states which major will require it:
   ```php
   if (null === $sourceMatcher) {
       trigger_deprecation(
           'setono/sylius-conversion-attribution-plugin',
           '2.1', // version the arg became "should be passed"
           'Not passing an instance of "%s" as argument "$sourceMatcher" to "%s()" is deprecated and will be required in 3.0.',
           SourceMatcherInterface::class,
           __METHOD__,
       );
   }
   ```
3. Guard every use of the argument against `null` so the old behaviour still works when it is absent.
4. Still inject it in the plugin's own XML service definition (also appended last) so normal installs get full functionality — the nullability only cushions downstream overrides.
5. Add a `@deprecated will be required in 3.0` comment on the property so it's greppable alongside the `trigger_deprecation()` call.

**Removing the shim in the next major:** grep the `src/` tree for `trigger_deprecation` — each hit is a nullable argument to make required (drop the `?`/`= null`, delete the `trigger_deprecation` block and the null-guards). Current shims: none (2.0 dropped all of 1.x's; see `UPGRADE.md`).

Test the legacy path by constructing the service without the new argument and asserting the deprecation fires (register a temporary `E_USER_DEPRECATED` handler with `set_error_handler()` around the construction). Mark such tests `@group legacy`.
