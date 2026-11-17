<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

/*
|--------------------------------------------------------------------------
| Resolve the Laravel application directory
|--------------------------------------------------------------------------
|
| On a standard Laravel install, this public directory sits directly
| inside the application root. On Hostinger, public_html may instead be
| a symlink to this directory while the application is deployed beside
| public_html in a private "laravel-app" directory.
|
*/

$projectRootCandidates = [
    dirname(__DIR__),
    dirname(__DIR__).'/laravel-app',
];

$projectRoot = null;

foreach ($projectRootCandidates as $candidate) {
    if (
        is_file($candidate.'/vendor/autoload.php')
        && is_file($candidate.'/bootstrap/app.php')
    ) {
        $projectRoot = $candidate;
        break;
    }
}

if ($projectRoot === null) {
    error_log('Laravel bootstrap failed: application root could not be located from the public entry point.');
    http_response_code(500);
    exit('The application is temporarily unavailable.');
}

if (file_exists($maintenance = $projectRoot.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $projectRoot.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once $projectRoot.'/bootstrap/app.php';

// Keep Laravel's public asset/storage paths aligned with the actual web root.
$app->usePublicPath(__DIR__);

$app->handleRequest(Request::capture());
