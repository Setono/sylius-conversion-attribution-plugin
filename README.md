# Sylius Conversion Attribution Plugin

[![Latest Version][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE)
[![Build Status][ico-github-actions]][link-github-actions]
[![Code Coverage][ico-code-coverage]][link-code-coverage]
[![Mutation testing][ico-infection]][link-infection]

See where customers are coming from by attributing conversions directly in your Sylius store

## Installation

### Download plugin

```bash
composer require setono/sylius-conversion-attribution-plugin
```

Notice that this also installs the `setono/client-bundle` which is required by this plugin to work properly.

If you want to use the default javascript injection, you should also install the `setono/tag-bag-bundle`.

See the installation instructions for the `setono/tag-bag-bundle` [here](https://github.com/Setono/TagBagBundle).

### Import routes
    
```yaml
# config/routes/setono_sylius_conversion_attribution.yaml

setono_sylius_conversion_attribution:
    resource: "@SetonoSyliusConversionAttributionPlugin/Resources/config/routes.yaml"
```

### Extend `Customer` and `Order` entities
    
```php
<?php

declare(strict_types=1);

namespace App\Entity\Customer;

use Doctrine\ORM\Mapping as ORM;
use Setono\SyliusConversionAttributionPlugin\Model\CustomerInterface;
use Setono\SyliusConversionAttributionPlugin\Model\CustomerTrait;
use Sylius\Component\Core\Model\Customer as BaseCustomer;

/**
 * @ORM\Entity
 *
 * @ORM\Table(name="sylius_customer")
 */
class Customer extends BaseCustomer implements CustomerInterface
{
    use CustomerTrait;
}
```

```php
<?php

declare(strict_types=1);

namespace App\Entity\Order;

use Doctrine\ORM\Mapping as ORM;
use Setono\SyliusConversionAttributionPlugin\Model\OrderInterface;
use Setono\SyliusConversionAttributionPlugin\Model\OrderTrait;
use Sylius\Component\Core\Model\Order as BaseOrder;

/**
 * @ORM\Entity
 *
 * @ORM\Table(name="sylius_order")
 */
class Order extends BaseOrder implements OrderInterface
{
    use OrderTrait;
}
```

### Migrate your database

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

### Prune old sources (recommended)

Every tracked hit creates a `source` row, so the table grows continuously. Schedule the prune
command (for example daily via cron) to delete old rows. By default it keeps 180 days:

```bash
php bin/console setono:sylius-conversion-attribution:prune
```

## Notes

### Optional runtime dependencies

- The default JavaScript injection requires [`setono/tag-bag-bundle`](https://github.com/Setono/TagBagBundle).
  Without it, enabling the `javascript` feature throws at container build time. Install it, or set
  `setono_sylius_conversion_attribution.javascript.enabled: false` and inject the tracking snippet yourself.
- Bot filtering (for the `/track` endpoint) is provided by `setono/bot-detection-bundle`, which is
  installed automatically as a dependency. Most bots are filtered structurally anyway: they don't
  execute the JavaScript snippet, so they never POST to `/track`.

### Full page cache

The injected tracking snippet is static — byte-identical for every visitor — so pages carrying it
are safe to store in a full page / HTTP cache (Varnish, a CDN, Symfony HttpCache, …). Nothing
per-visitor is rendered into the HTML: the client id travels on the `setono_client_id` cookie that
accompanies the snippet's `POST /track` request, and the traffic source is matched server side at
POST time from the posted page URL and referrer. The session throttle (`session_timeout`) is
evaluated in the browser against a rolling `localStorage` timestamp; a query string or a cross-host
referrer bypasses it so campaign clicks are never lost.

Two operational notes:

- **Purge your page cache after upgrading to 1.2.** Pages cached with the pre-1.2 snippet keep
  POSTing the client id that was baked in when the cache was filled (the `/track` endpoint still
  accepts those payloads, so nothing breaks — but their attribution stays wrong until they expire).
- Make sure your layout renders the tag bag on cacheable pages. If a page never renders it, the tag
  bag stores the pending snippet in the session, which can make the response uncacheable.

If your cache strips `Set-Cookie` from cached responses (it generally should), first-time visitors
get their client cookie on the uncached `POST /track` response instead, so attribution still works.

### Privacy / GDPR

A `source` row stores the visitor's IP address and user agent, which are personal data in many
jurisdictions. Keep retention short by scheduling the prune command, and ensure your privacy policy
and consent handling cover this collection (you may need to anonymize or omit the IP depending on your
requirements).

[ico-version]: https://poser.pugx.org/setono/sylius-conversion-attribution-plugin/v/stable
[ico-license]: https://poser.pugx.org/setono/sylius-conversion-attribution-plugin/license
[ico-github-actions]: https://github.com/Setono/sylius-conversion-attribution-plugin/workflows/build/badge.svg
[ico-code-coverage]: https://codecov.io/gh/Setono/sylius-conversion-attribution-plugin/graph/badge.svg
[ico-infection]: https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2FSetono%2Fsylius-conversion-attribution-plugin%2Fmaster

[link-packagist]: https://packagist.org/packages/setono/sylius-conversion-attribution-plugin
[link-github-actions]: https://github.com/Setono/sylius-conversion-attribution-plugin/actions
[link-code-coverage]: https://codecov.io/gh/Setono/sylius-conversion-attribution-plugin
[link-infection]: https://dashboard.stryker-mutator.io/reports/github.com/Setono/sylius-conversion-attribution-plugin/master
