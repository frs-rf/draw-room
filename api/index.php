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

// Fallback BROADCAST_CONNECTION to null if Pusher is chosen but keys are not configured
$pusherKey = $_ENV['PUSHER_APP_KEY'] ?? ($_SERVER['PUSHER_APP_KEY'] ?? getenv('PUSHER_APP_KEY'));
if (empty($pusherKey)) {
    $_ENV['BROADCAST_CONNECTION'] = 'null';
    $_SERVER['BROADCAST_CONNECTION'] = 'null';
    putenv('BROADCAST_CONNECTION=null');
}

// Ensure DB_CONNECTION is pgsql
$_ENV['DB_CONNECTION'] = 'pgsql';
putenv('DB_CONNECTION=pgsql');

// Sanitize, normalize, and extract DATABASE_URL
$rawDbUrl = $_ENV['DATABASE_URL'] ?? ($_SERVER['DATABASE_URL'] ?? (getenv('DATABASE_URL') ?: ($_ENV['DB_URL'] ?? '')));
if ($rawDbUrl && preg_match('/postgres(?:ql)?:\/\/[^\s"\']+/i', $rawDbUrl, $m)) {
    $urlBase = explode('?', $m[0])[0];
    $parts = parse_url($urlBase);

    $dbName = isset($parts['path']) ? trim($parts['path'], '/') : 'neondb';
    if (str_contains($dbName, '&')) {
        $dbName = explode('&', $dbName)[0];
    }

    $cleanUrl = sprintf(
        'postgresql://%s:%s@%s:%s/%s?sslmode=require',
        $parts['user'] ?? 'neondb_owner',
        $parts['pass'] ?? '',
        $parts['host'] ?? '127.0.0.1',
        $parts['port'] ?? 5432,
        $dbName
    );

    $_ENV['DATABASE_URL'] = $cleanUrl;
    $_ENV['DB_URL'] = $cleanUrl;
    $_SERVER['DATABASE_URL'] = $cleanUrl;
    $_SERVER['DB_URL'] = $cleanUrl;
    putenv('DATABASE_URL=' . $cleanUrl);
    putenv('DB_URL=' . $cleanUrl);

    if (!empty($parts['host'])) {
        $_ENV['DB_HOST'] = $parts['host'];
        putenv('DB_HOST=' . $parts['host']);

        $_ENV['DB_PORT'] = (string)($parts['port'] ?? '5432');
        putenv('DB_PORT=' . $_ENV['DB_PORT']);

        if (!empty($parts['user'])) {
            $_ENV['DB_USERNAME'] = urldecode($parts['user']);
            putenv('DB_USERNAME=' . $_ENV['DB_USERNAME']);
        }
        if (!empty($parts['pass'])) {
            $_ENV['DB_PASSWORD'] = urldecode($parts['pass']);
            putenv('DB_PASSWORD=' . $_ENV['DB_PASSWORD']);
        }
        $_ENV['DB_DATABASE'] = $dbName;
        putenv('DB_DATABASE=' . $dbName);

        $_ENV['DB_SSLMODE'] = 'require';
        putenv('DB_SSLMODE=require');
    }
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
