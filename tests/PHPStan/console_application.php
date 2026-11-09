<?php

declare(strict_types=1);

use Setono\SyliusConversionAttributionPlugin\Tests\Application\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;

// Loads the test environment's variables (e.g. APP_SECRET) like bin/console does, as building a command's
// services can resolve them
$_SERVER['APP_ENV'] = 'test';
require __DIR__ . '/../Application/config/bootstrap.php';

$kernel = new Kernel('test', true);
$kernel->boot();

return new Application($kernel);
