<?php

/**
 * Serverless bridge for Vercel
 */

// Define storage location in the writable /tmp filesystem
$storagePath = '/tmp/storage';
$_ENV['LARAVEL_STORAGE_PATH'] = $storagePath;
$_ENV['APP_CONFIG_CACHE'] = $_ENV['APP_CONFIG_CACHE'] ?? '/tmp/config.php';
$_ENV['APP_EVENTS_CACHE'] = $_ENV['APP_EVENTS_CACHE'] ?? '/tmp/events.php';
$_ENV['APP_PACKAGES_CACHE'] = $_ENV['APP_PACKAGES_CACHE'] ?? '/tmp/packages.php';
$_ENV['APP_ROUTES_CACHE'] = $_ENV['APP_ROUTES_CACHE'] ?? '/tmp/routes.php';
$_ENV['APP_SERVICES_CACHE'] = $_ENV['APP_SERVICES_CACHE'] ?? '/tmp/services.php';
$_ENV['VIEW_COMPILED_PATH'] = $_ENV['VIEW_COMPILED_PATH'] ?? $storagePath . '/framework/views';

// Fallback APP_KEY if not yet set in Vercel Environment Variables
if (empty($_ENV['APP_KEY']) && empty($_SERVER['APP_KEY']) && !getenv('APP_KEY')) {
    $_ENV['APP_KEY'] = 'base64:oZXQDC1ako8W2Nc0YjUSj+uG/CbWJoEQQgGYqrZl7TE=';
    putenv('APP_KEY=base64:oZXQDC1ako8W2Nc0YjUSj+uG/CbWJoEQQgGYqrZl7TE=');
}

// Fallback DB_URL from DATABASE_URL
$dbUrl = $_ENV['DATABASE_URL'] ?? ($_SERVER['DATABASE_URL'] ?? getenv('DATABASE_URL'));
if ($dbUrl && empty($_ENV['DB_URL'])) {
    $_ENV['DB_URL'] = $dbUrl;
    putenv('DB_URL=' . $dbUrl);
}

// Ensure necessary directory tree exists in /tmp
$dirs = [
    $storagePath,
    $storagePath . '/app',
    $storagePath . '/app/public',
    $storagePath . '/framework',
    $storagePath . '/framework/cache',
    $storagePath . '/framework/cache/data',
    $storagePath . '/framework/sessions',
    $storagePath . '/framework/views',
    $storagePath . '/logs',
];

foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Forward execution to Laravel's public entrypoint
require __DIR__ . '/../public/index.php';
