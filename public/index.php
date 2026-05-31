<?php
declare(strict_types=1);

define('FLINT_START', microtime(true));
define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/vendor/autoload.php';

$app = new Flint\Application(BASE_PATH);
$app->boot();
$app->handleRequest();
