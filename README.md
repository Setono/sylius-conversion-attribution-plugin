# Sylius Conversion Attribution Plugin

[![Latest Version][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE)
[![Build Status][ico-github-actions]][link-github-actions]
[![Code Coverage][ico-code-coverage]][link-code-coverage]
[![Mutation testing][ico-infection]][link-infection]

See where customers are coming from by attributing conversions directly in your Sylius store

Upgrading from 1.x? See [UPGRADE.md](UPGRADE.md).

## Installation

### Download plugin

```bash
composer require setono/sylius-conversion-attribution-plugin
```

This also installs [`setono/client-bundle`](https://github.com/Setono/ClientBundle), which identifies
visitors with the `setono_client_id` cookie, and [`setono/bot-detection-bundle`](https://github.com/Setono/BotDetectionBundle).

The tracking script tag is added to your pages through [`setono/tag-bag-bundle`](https://github.com/Setono/TagBagBundle)
(3.1 or later), so install it too and follow its installation instructions:

```bash
composer require setono/tag-bag-bundle
```

If you'd rather render the script tag yourself, disable the `javascript` feature instead (see [Configuration](#configuration)).

### Register the plugin

Symfony Flex registers the client, bot detection and tag bag bundles, but not this plugin, so add it to
`config/bundles.php`:

```php
<?php

return [
    // ...
    Setono\SyliusConversionAttributionPlugin\SetonoSyliusConversionAttributionPlugin::class => ['all' => true],
];
```

### Import routes
    
```yaml
# config/routes/setono_sylius_conversion_attribution.yaml

setono_sylius_conversion_attribution:
    resource: "@SetonoSyliusConversionAttributionPlugin/Resources/config/routes.yaml"
```

### Extend the `Order` entity

```php
<?php

declare(strict_types=1);

namespace App\Entity\Order;

use Doctrine\ORM\Mapping as ORM;
use Setono\SyliusConversionAttributionPlugin\Model\OrderInterface;
use Setono\SyliusConversionAttributionPlugin\Model\OrderTrait;
use Sylius\Component\Core\Model\Order as BaseOrder;

#[ORM\Entity]
#[ORM\Table(name: 'sylius_order')]
class Order extends BaseOrder implements OrderInterface
{
    use OrderTrait;
}
```

The examples use attribute mapping, like Sylius-Standard. The traits are mapped with both attributes and annotations,
so if your application maps its entities with annotations, map the class with `@ORM\Entity` and `@ORM\Table` instead.

### Extend the `Customer` entity (optional)

With this, a customer collects the client ids of the browsers they checked out from, and their orders are also
credited to page views from those other browsers (see [Attribution](#attribution) and [Privacy / GDPR](#privacy--gdpr)).
Without it, only the browser an order was checked out from counts.

```php
<?php

declare(strict_types=1);

namespace App\Entity\Customer;

use Doctrine\ORM\Mapping as ORM;
use Setono\SyliusConversionAttributionPlugin\Model\CustomerInterface;
use Setono\SyliusConversionAttributionPlugin\Model\CustomerTrait;
use Sylius\Component\Core\Model\Customer as BaseCustomer;

#[ORM\Entity]
#[ORM\Table(name: 'sylius_customer')]
class Customer extends BaseCustomer implements CustomerInterface
{
    use CustomerTrait;
}
```

### Migrate your database

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

### Install assets

The tracking script is shipped as a bundle asset, so make sure assets are installed (Sylius'
installation and deployment already do this):

```bash
php bin/console assets:install
```

If the script isn't installed, the browser gets a 404 for it and nothing is tracked.

### Schedule the attribute and prune commands

Orders are attributed by a command rather than during the checkout, so recording an attribution can never make
a checkout fail. Schedule it every few minutes, and the prune command daily, for example with cron:

```
*/5 * * * * php /path/to/your/app/bin/console setono:sylius-conversion-attribution:attribute
0 3 * * * php /path/to/your/app/bin/console setono:sylius-conversion-attribution:prune
```

A scheduled run only considers orders completed within the lookback window (30 days by default), as older orders
have no page views left to credit once they're pruned. Run it with `--all` to also attribute older orders, e.g.
once after installing the plugin or if the schedule stopped for longer than the window. On a shop with many
orders, add an index on `sylius_order.checkout_completed_at` (Sylius doesn't have one), so each run finds the
recent orders quickly.

The attribute command takes a lock through your application's lock factory, so overlapping runs don't attribute an
order twice. With several servers, configure a shared store under `framework.lock` (e.g. Redis), or run it on one. The prune command deletes the tracked page views older than the lookback window
(30 days by default), which can't be credited anymore. Every tracked page view creates a `source` row, so
without pruning the table grows continuously.

## Configuration

All options are optional. These are the defaults:

```yaml
# config/packages/setono_sylius_conversion_attribution.yaml
setono_sylius_conversion_attribution:
    # Adds the tracking script tag to HTML pages (requires setono/tag-bag-bundle)
    javascript: true

    # Seconds of inactivity after which a visitor's next page view is tracked again
    session_timeout: 1800

    # Days before checkout a tracked page view can be credited for an order. The prune command keeps page
    # views this long
    lookback_window: 30
```

`query_parameters` maps query parameters, typically ad click ids, to a traffic source. The defaults are:

| Key         | Query parameters            | Source                | Medium   |
|-------------|-----------------------------|-----------------------|----------|
| `facebook`  | `fbclid`                    | `facebook`            | `social` |
| `google`    | `gclid`, `gbraid`, `wbraid` | `google`              | `cpc`    |
| `microsoft` | `msclkid`                   | `bing`                | `cpc`    |
| `generic`   | `source`, `ref`             | the parameter's value |          |
| `tiktok`    | `ttclid`                    | `tiktok`              | `cpc`    |
| `x`         | `twclid`                    | `x`                   | `cpc`    |

Facebook adds `fbclid` to links in general, not just to ads, so it gets the medium `social`, the one the
referrer database gives facebook.com. Add UTM parameters to your Facebook ads' links (e.g.
`utm_source=facebook&utm_medium=paid_social&utm_campaign=…`), which win over it (see
[Traffic sources](#traffic-sources)).

Add your own, or disable a default by its key:

```yaml
setono_sylius_conversion_attribution:
    query_parameters:
        pinterest:
            matches: [epik]
            source: pinterest # without a source, the parameter's value is used
            medium: cpc # optional, like campaign
        generic:
            enabled: false
```

`referrers` maps the sites a referral comes from to a source, and wins over the referrer database
(see [Traffic sources](#traffic-sources)). A host covers its subdomains too. The default is:

| Key | Hosts                          | Source | Medium   |
|-----|--------------------------------|--------|----------|
| `x` | `t.co`, `twitter.com`, `x.com` | `x`    | `social` |

The referrer database lists t.co and twitter.com as Twitter and doesn't know x.com, while the `twclid`
click id counts as `x`, so the default keeps X under one name. Your own UTM parameters still win: a link
tagged only `utm_source=twitter` names another source than `x`, so it's stored as `twitter` without the
referral's medium. Tag X links `utm_source=x`, or set `utm_medium` too. Add your own, or disable the
default by its key:

```yaml
setono_sylius_conversion_attribution:
    referrers:
        partner:
            hosts: [partner.example]
            source: partner
            medium: affiliate # optional; without a medium, a referral counts as referral
        x:
            enabled: false
```

## How it works

### Tracking

On every HTML page the plugin adds this tag (through the tag bag):

```html
<script src="/bundles/setonosyliusconversionattributionplugin/js/track.js?v=…" id="ssca" defer
        data-endpoint="/track" data-session-timeout="1800"></script>
```

The script POSTs the page URL and the referrer to `/track`, where everything else is resolved server
side: the client id from the `setono_client_id` cookie riding on the request, and the page view's source
from the page's query parameters (click ids such as `gclid`/`fbclid`, UTM parameters) and the referrer
(see [Traffic sources](#traffic-sources)). To keep
the `source` table small, a visitor isn't tracked again within `session_timeout` seconds (a rolling
`localStorage` timestamp): only the first page view of a session is tracked, with its URL and
referrer, since that's where the visitor came from. Later page views within the session aren't, not
even with campaign parameters or a referrer from another site, so a customer returning from a payment
gateway (PayPal, Stripe, …) is never recorded as a referral. Prerendered pages are tracked when they are shown, not before.

### Traffic sources

A tracked page view gets its source in two steps. First, the source matchers are tried in order, and
the first that matches wins:

1. A query parameter from `query_parameters` (`gclid`, `fbclid`, …, see [Configuration](#configuration))
2. The referrer. Sites in `referrers` (see [Configuration](#configuration)) come first. Search engines,
   social networks and email providers are looked up in
   [Snowplow's referer database](https://github.com/snowplow-referer-parser/referer-parser), which is
   downloaded when the cache is warmed up (if the download fails, a warning is logged and the lookup
   is skipped until the next warmup, while `referrers` still apply). A site without a medium (e.g.
   Google Maps in the database) gets the medium `referral`, and any other site becomes its host with
   the medium `referral`. Referrers from your own host are ignored.

Then the UTM parameters (`utm_source`, `utm_medium` and `utm_campaign`) are applied on top of that
match. They're the one input you set on purpose, while a click id or a referrer is inferred, and
auto-tagged links often carry only some of them:

- Each UTM parameter that is set wins over the same field of the match. `?gclid=…&utm_campaign=spring_sale`
  is credited to `google` / `cpc` / `spring_sale`.
- If `utm_source` names another source than the match (ignoring case), nothing else of the match is
  kept. A newsletter link (`?utm_source=newsletter`) shared on Facebook is credited to `newsletter`,
  without Facebook's medium.
- UTM parameters without a `utm_source` keep the match's source or, if nothing matched, get the source
  `(not set)`. `?utm_medium=email&utm_campaign=black-friday` opened in a mail app that sends no referrer
  is credited to `(not set)` / `email` / `black-friday`. Unlike `direct`, `(not set)` counts as
  non-direct when an order is [attributed](#attribution).

A blank parameter counts as missing. If nothing matches and no UTM parameter is set, the source is
`direct`.

Sources and mediums are stored in lowercase, whichever rule found them, so one site has one name: the
referrer database's `Google` and `utm_source=Google` are both stored as `google`. Campaigns keep their
spelling.

A query parameter that isn't UTF-8, e.g. `?utm_campaign=sommerf%E6st` from a tool that encodes links in
Latin-1, is read as Windows-1252 (which extends Latin-1), so the campaign is `sommerfæst`. Emoji and other
characters outside the Basic Multilingual Plane are stored as `�` on every database, since a table created with
MySQL's `utf8` (utf8mb3) charset, Sylius' default up to 1.9, can't hold them: `?utm_campaign=🎄xmas` is shown as
`�xmas` in the admin. A source, medium or campaign longer than 255 characters is stored cut to its first 255.

To add your own rule, implement `SourceMatcherInterface` and tag the service. The priority decides
its place among the matchers: the built-in ones have 300 (query parameters) and 100 (referrer).
Whatever its priority, the UTM parameters are applied to its match too.

```php
<?php

declare(strict_types=1);

namespace App\Attribution;

use Setono\SyliusConversionAttributionPlugin\Matcher\PageView;
use Setono\SyliusConversionAttributionPlugin\Matcher\Source;
use Setono\SyliusConversionAttributionPlugin\Matcher\SourceMatcherInterface;

final class NewsletterSourceMatcher implements SourceMatcherInterface
{
    public function match(PageView $pageView): ?Source
    {
        // $pageView->url, $pageView->referrer, $pageView->host and $pageView->referrerHost are available
        // too. Values come trimmed, a blank query parameter or referrer is null, and a query parameter that
        // isn't UTF-8 is read as Windows-1252
        $issue = $pageView->getQueryParameter('newsletter');
        if (null === $issue) {
            return null;
        }

        return new Source('newsletter', 'email', $issue);
    }
}
```

```yaml
# config/services.yaml
services:
    App\Attribution\NewsletterSourceMatcher:
        tags:
            - { name: setono_sylius_conversion_attribution.source_matcher, priority: 250 }
```

### Attribution

When a customer completes checkout, the visitor's client id is saved on the order and, if you
[extended the `Customer` entity](#extend-the-customer-entity-optional), added to the customer's client ids. The
attribute command then records the order's attribution: the newest non-direct page view tracked for any of those
client ids in the lookback window (30 days) before checkout or, if there is none, the newest direct one, with its
source, medium, campaign, referrer, landing page and time, or nothing if no page view was tracked. So a customer who
checked out from their phone before and now orders on their laptop is credited to the ad they clicked on their
phone. The customer's client ids are read when the order is attributed, so with `--all` a browser the customer
first checked out from after the order counts too, though only with page views tracked before the order's checkout.
The order page in the admin shows the attribution in the sidebar, or that the order hasn't been attributed
yet. Page views tracked after checkout, like the customer returning from a payment gateway, aren't credited, and
pruning old page views doesn't change the attribution.

Only orders completed through the shop's checkout get a client id. Orders completed through Sylius' API (e.g. from a
headless frontend) aren't attributed. The tracking script only runs on pages Sylius renders, so the plugin doesn't
track page views on such a frontend either.

## Notes

### Optional runtime dependencies

- The default JavaScript injection requires [`setono/tag-bag-bundle`](https://github.com/Setono/TagBagBundle).
  Without it, the container fails to build while the `javascript` feature is enabled (the default).
  Install it, or set `setono_sylius_conversion_attribution.javascript: false` and render the script
  tag above yourself.
- Bot filtering (for the `/track` endpoint) is provided by `setono/bot-detection-bundle`, which is
  installed automatically as a dependency. Most bots are filtered structurally anyway: they don't
  execute JavaScript, so they never POST to `/track`. Automated browsers that *do* execute JavaScript
  (Selenium, Puppeteer, Playwright, Cypress, headless Chrome, …) are skipped by the script itself via
  the standardized `navigator.webdriver` flag (and Cypress' `window.Cypress`). This also keeps your
  own end-to-end test runs out of the attribution data.

### Full page cache

The script tag is static (identical for every visitor), so pages carrying it are safe to store in a
full page / HTTP cache (Varnish, a CDN, Symfony HttpCache, …). Nothing per-visitor is rendered into
the HTML. Make sure your layout renders the tag bag on cacheable pages: if a page never renders it,
the tag bag stores the pending tag in the session, which can make the response uncacheable.

If your cache strips `Set-Cookie` from cached responses (it generally should), first-time visitors
get their client cookie on the uncached `POST /track` response instead, so attribution still works.

### Content Security Policy

The script is a same-origin file, not an inline script, so a strict CSP needs no `'unsafe-inline'`,
hashes or nonces (nonces couldn't work with full page caching anyway). Allow the script in
`script-src` (`'self'`, or your asset CDN if you serve assets from one) and the `/track` endpoint in
`connect-src`:

```
Content-Security-Policy: script-src 'self'; connect-src 'self'
```

### Privacy / GDPR

A `source` row stores the visitor's IP address and user agent, which are personal data in many
jurisdictions. Keep retention short by scheduling the prune command, and ensure your privacy policy
and consent handling cover this collection (you may need to anonymize or omit the IP depending on your
requirements). On the visitor's device, the client id lives in the `setono_client_id` cookie and the
script keeps its session timestamp in `localStorage` (`ssca_last_seen`).

If you extended the `Customer` entity, each customer keeps the client ids of all the browsers they checked out
from, which links those browsers' page views to them. Customers with an account have to log in to check out with
their email, but Sylius gives guest checkouts with the same email one customer, so a guest who enters the email of a
customer without an account links their browser to that customer. If you don't want browsers linked, leave the
`Customer` entity as it is: only the browser an order was checked out from is credited then.

[ico-version]: https://poser.pugx.org/setono/sylius-conversion-attribution-plugin/v/stable
[ico-license]: https://poser.pugx.org/setono/sylius-conversion-attribution-plugin/license
[ico-github-actions]: https://github.com/Setono/sylius-conversion-attribution-plugin/workflows/build/badge.svg
[ico-code-coverage]: https://codecov.io/gh/Setono/sylius-conversion-attribution-plugin/graph/badge.svg
[ico-infection]: https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2FSetono%2Fsylius-conversion-attribution-plugin%2F2.x

[link-packagist]: https://packagist.org/packages/setono/sylius-conversion-attribution-plugin
[link-github-actions]: https://github.com/Setono/sylius-conversion-attribution-plugin/actions
[link-code-coverage]: https://codecov.io/gh/Setono/sylius-conversion-attribution-plugin
[link-infection]: https://dashboard.stryker-mutator.io/reports/github.com/Setono/sylius-conversion-attribution-plugin/2.x
