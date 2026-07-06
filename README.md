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
- Bot filtering (both for the injected snippet and the `/track` endpoint) is provided by
  `setono/bot-detection-bundle`, which is installed automatically as a dependency.

### Full page cache

The injected tracking snippet embeds a server-resolved client id. If a full page / HTTP cache stores
the rendered HTML, every visitor served that cached page shares the same embedded client id, which
corrupts attribution. Exclude pages carrying the snippet from full page caching, or only enable the
feature on responses that are not cached.

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
