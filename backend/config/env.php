<?php

/**
 * Environment Configuration Loader
 *
 * Reads the .env file from the project root and populates
 * $_ENV and putenv() for consistent access throughout the app.
 */

declare(strict_types=1);

// Force PHP to use Nigerian Time
date_default_timezone_set('Africa/Lagos');

function loadEnv(string $path): void
{
    if (!file_exists($path)) {
        throw new RuntimeException("Environment file not found: {$path}");
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if ($lines === false) {
        throw new RuntimeException("Failed to read environment file: {$path}");
    }

    foreach ($lines as $line) {
        // Skip comments
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        // Parse KEY=VALUE
        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) {
            continue;
        }

        $key = trim($parts[0]);
        $value = trim($parts[1]);

        // Remove surrounding quotes if present
        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        $_ENV[$key] = $value;
        putenv("{$key}={$value}");
    }
}

/**
 * Get an environment variable with an optional default.
 */
function env(string $key, string $default = ''): string
{
    return $_ENV[$key] ?? getenv($key) ?: $default;
}

// Auto-load .env from backend root
$envPath = dirname(__DIR__) . '/.env';
if (file_exists($envPath)) {
    loadEnv($envPath);
}
