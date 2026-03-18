<?php

/**
 * JSON Response Helper
 *
 * Provides consistent JSON response formatting with CORS
 * headers for the Next.js frontend.
 */

declare(strict_types=1);

class Response
{
    /**
     * Send a successful JSON response.
     */
    public static function success(mixed $data = null, string $message = 'Success', int $code = 200): void
    {
        self::send($code, [
            'status' => true,
            'message' => $message,
            'data' => $data,
        ]);
    }

    /**
     * Send an error JSON response.
     */
    public static function error(string $message, int $code = 400, mixed $errors = null): void
    {
        $payload = [
            'status' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        self::send($code, $payload);
    }

    /**
     * Send the JSON response with headers.
     */
    private static function send(int $code, array $payload): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');

        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Set CORS headers for development.
     * In production, restrict the origin.
     */
    public static function cors(): void
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';

        header("Access-Control-Allow-Origin: {$origin}");
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Max-Age: 86400');

        // Handle preflight
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }
}
