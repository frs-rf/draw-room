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
