<?php

declare(strict_types=1);

// Router for PHP's built-in web server: serves existing files (assets) as is and everything else
// through the Symfony front controller, also paths containing a dot (e.g. liip's /media/cache/resolve/...)
$publicDir = dirname(__DIR__, 2) . '/Application/public';

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', \PHP_URL_PATH);
if (is_string($path) && is_file($publicDir . $path)) {
    return false;
}

// The built-in server doesn't expose the process environment in $_SERVER (unlike php-fpm with
// clear_env = no), so Symfony's Dotenv would let .env's APP_ENV=dev win over the APP_ENV it was started with
foreach (getenv() as $name => $value) {
    $_SERVER[$name] ??= $value;
}

$_SERVER['SCRIPT_FILENAME'] = $publicDir . '/index.php';
$_SERVER['SCRIPT_NAME'] = '/index.php';

require dirname(__DIR__, 2) . '/Application/public/index.php';
