<?php

/**
 * API Router Entry Point
 *
 * Minimal router that maps incoming requests to the appropriate
 * route handler. Runs as a front-controller via .htaccess or
 * PHP's built-in server.
 *
 * Usage (dev):
 *   php -S localhost:8000 index.php
 */

declare(strict_types=1);

require_once __DIR__ . '/config/env.php';
require_once __DIR__ . '/lib/Response.php';

// ── Set CORS headers ────────────────────────────────────────────────
Response::cors();

// ── Parse request ───────────────────────────────────────────────────
$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Normalize: strip trailing slash, ensure leading slash
$uri = '/' . trim($uri, '/');

// ── Route definitions ───────────────────────────────────────────────
$routes = [
    'POST /api/initialize' => __DIR__ . '/routes/initialize.php',
    'GET /api/verify' => __DIR__ . '/routes/verify.php',
    'GET /api/transactions' => __DIR__ . '/routes/transactions.php',
    'POST /api/webhook' => __DIR__ . '/routes/webhook.php',
];

$routeKey = "{$method} {$uri}";

// ── Health check ────────────────────────────────────────────────────
if ($uri === '/api/health') {
    Response::success(['uptime' => time()], 'Payment API is running');
}

// ── Dispatch ────────────────────────────────────────────────────────
if (isset($routes[$routeKey])) {
    require $routes[$routeKey];
    exit;
}

// ── 404 ─────────────────────────────────────────────────────────────
Response::error('Endpoint not found', 404);
