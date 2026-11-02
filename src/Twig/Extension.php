<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class Extension extends AbstractExtension
{
    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('setono_sylius_conversion_attribution_last_non_direct', [Runtime::class, 'getLastNonDirect']),
        ];
    }
}
