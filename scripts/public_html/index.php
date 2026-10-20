<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

/*
|--------------------------------------------------------------------------
| Hostinger public_html entry point
|--------------------------------------------------------------------------
|
| This file lives in public_html/ (outside the git repo).
| It bootstraps Laravel from ../canteen_management/
|
| One-time setup: bash scripts/setup-public-html.sh
|
*/

if (file_exists($maintenance = __DIR__.'/../canteen_management/storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__.'/../canteen_management/vendor/autoload.php';

/** @var Application $app */
$app = require_once __DIR__.'/../canteen_management/bootstrap/app.php';

$app->handleRequest(Request::capture());
