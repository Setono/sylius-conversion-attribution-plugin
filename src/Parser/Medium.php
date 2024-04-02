<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Parser;

enum Medium: string
{
    case search = 'search';
    case social = 'social';
    case unknown = 'unknown';
    case internal = 'internal';
    case email = 'email';
    case invalid = 'invalid';
}
