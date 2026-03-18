<?php

/**
 * Paystack HTTP Client
 *
 * Centralized cURL-based client for all Paystack API calls.
 * Features:
 *   - Exponential backoff retry (3 attempts, 1s → 2s → 4s)
 *   - Retries only on 5xx errors and network failures
 *   - Full audit logging of every attempt
 *   - Sanitized request logging (no secret keys)
 */

declare(strict_types=1);

require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/../config/env.php';

class PaystackClient
{
    private const BASE_URL = 'https://api.paystack.co';
    private const MAX_RETRIES = 3;
    private const BASE_DELAY_SEC = 1;
    private const TIMEOUT_SECONDS = 30;

    /** cURL error codes that are safe to retry */
    private const RETRYABLE_CURL_ERRORS = [
        CURLE_COULDNT_CONNECT,
        CURLE_OPERATION_TIMEOUTED,
        CURLE_GOT_NOTHING,
        CURLE_RECV_ERROR,
        CURLE_SEND_ERROR,
    ];

    private string $secretKey;

    public function __construct()
    {
        $this->secretKey = env('PAYSTACK_SECRET_KEY');

        if (empty($this->secretKey)) {
            throw new RuntimeException('PAYSTACK_SECRET_KEY is not configured.');
        }
    }

    // ─── Paystack API Methods ────────────────────────────────────────

    /**
     * Initialize a transaction.
     *
     * @param array{email: string, amount: int, reference: string, callback_url?: string, currency?: string, metadata?: array} $params
     * @return array Paystack response data
     */
    public function initializeTransaction(array $params): array
    {
        return $this->request('POST', '/transaction/initialize', $params, $params['reference'] ?? 'unknown', 'initialize');
    }

    /**
     * Verify a transaction by its reference.
     *
     * @param string $reference Transaction reference
     * @return array Paystack response data
     */
    public function verifyTransaction(string $reference): array
    {
        return $this->request('GET', "/transaction/verify/{$reference}", null, $reference, 'verify');
    }

    /**
     * List transactions with optional filters.
     *
     * @param array{perPage?: int, page?: int, status?: string, from?: string, to?: string} $params
     * @return array Paystack response data
     */
    public function listTransactions(array $params = []): array
    {
        $query = http_build_query(array_filter($params));
        $path = '/transaction' . ($query ? "?{$query}" : '');

        return $this->request('GET', $path, null, 'list', 'list');
    }

    // ─── Core HTTP Engine with Retry ─────────────────────────────────

    /**
     * Execute an HTTP request to Paystack with exponential backoff retry.
     *
     * @param string     $method    HTTP method (GET, POST)
     * @param string     $path      API path (e.g., /transaction/initialize)
     * @param array|null $body      Request body for POST requests
     * @param string     $reference Transaction reference for logging
     * @param string     $action    Action name for logging
     * @return array Parsed response data
     *
     * @throws RuntimeException If all retries are exhausted
     */
    private function request(
        string $method,
        string $path,
        ?array $body,
        string $reference,
        string $action
    ): array {
        $url = self::BASE_URL . $path;
        $lastError = '';
        $lastCode = 0;

        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            $ch = curl_init();

            // Base cURL options
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $this->secretKey,
                    'Content-Type: application/json',
                    'Cache-Control: no-cache',
                ],
            ]);

            // Set method-specific options
            if ($method === 'POST' && $body !== null) {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
            }

            $rawResponse = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            $curlErrno = curl_errno($ch);

            curl_close($ch);

            // ── Network / cURL failure ──
            if ($rawResponse === false || $curlErrno !== 0) {
                $lastError = "cURL error ({$curlErrno}): {$curlError}";
                $lastCode = 0;

                Logger::log($reference, $action, 'retry', $body, null, $lastError, $attempt);

                if ($attempt < self::MAX_RETRIES && in_array($curlErrno, self::RETRYABLE_CURL_ERRORS, true)) {
                    $this->backoff($attempt);
                    continue;
                }

                break;
            }

            $decoded = json_decode($rawResponse, true);

            // ── 2xx Success ──
            if ($httpCode >= 200 && $httpCode < 300) {
                Logger::log($reference, $action, 'success', $body, $decoded, null, $attempt);
                return $decoded;
            }

            // ── 4xx Client Error — DO NOT retry ──
            if ($httpCode >= 400 && $httpCode < 500) {
                $message = $decoded['message'] ?? 'Client error from Paystack';
                Logger::log($reference, $action, 'failed', $body, $decoded, "HTTP {$httpCode}: {$message}", $attempt);

                throw new RuntimeException("Paystack error: {$message}", $httpCode);
            }

            // ── 5xx Server Error — retryable ──
            $lastError = $decoded['message'] ?? "HTTP {$httpCode} from Paystack";
            $lastCode = $httpCode;

            Logger::log($reference, $action, 'retry', $body, $decoded, "HTTP {$httpCode}: {$lastError}", $attempt);

            if ($attempt < self::MAX_RETRIES) {
                $this->backoff($attempt);
            }
        }

        // All retries exhausted
        $finalMessage = "Paystack API request failed after " . self::MAX_RETRIES . " attempts. Last error: {$lastError}";
        Logger::log($reference, $action, 'failed', $body, null, $finalMessage, self::MAX_RETRIES);

        throw new RuntimeException($finalMessage, $lastCode);
    }

    /**
     * Exponential backoff: delay = BASE_DELAY * 2^(attempt-1)
     * Attempt 1 → 1s, Attempt 2 → 2s, Attempt 3 → 4s
     */
    private function backoff(int $attempt): void
    {
        $delay = self::BASE_DELAY_SEC * (2 ** ($attempt - 1));
        sleep($delay);
    }

    // ─── Webhook Signature Verification ──────────────────────────────

    /**
     * Verify that a webhook payload was genuinely sent by Paystack.
     *
     * @param string $payload   Raw request body
     * @param string $signature Value of the x-paystack-signature header
     * @return bool True if signature is valid
     */
    public static function verifyWebhookSignature(string $payload, string $signature): bool
    {
        $secret = env('PAYSTACK_SECRET_KEY');
        $computed = hash_hmac('sha512', $payload, $secret);

        return hash_equals($computed, $signature);
    }
}
