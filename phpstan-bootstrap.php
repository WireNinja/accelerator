<?php

declare(strict_types=1);

$appPath = __DIR__ . '/../../bootstrap/app.php';

if (file_exists($appPath)) {
    $app = require $appPath;

    if (! defined('LARAVEL_VERSION') && method_exists($app, 'version')) {
        define('LARAVEL_VERSION', $app->version());
    }
}
