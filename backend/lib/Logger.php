<?php

/**
 * Payment Audit Logger
 *
 * Logs every Paystack API interaction to the payment_logs table
 * for debugging, retry tracking, and audit compliance.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class Logger
{
    /**
     * Log an API call attempt.
     *
     * @param string      $reference    Transaction reference
     * @param string      $action       Action type (initialize, verify, list, webhook)
     * @param string      $status       Result status (success, failed, retry)
     * @param array|null  $request      Request payload (sanitized — no secrets)
     * @param array|null  $response     Response payload
     * @param string|null $errorMessage Error details if failed
     * @param int         $attempt      Attempt number (1-based)
     */
    public static function log(
        string $reference,
        string $action,
        string $status,
        ?array $request = null,
        ?array $response = null,
        ?string $errorMessage = null,
        int $attempt = 1
    ): void {
        try {
            $db = getDatabase();

            $stmt = $db->prepare('
                INSERT INTO payment_logs
                    (reference, action, status, request_body, response_body, error_message, attempt)
                VALUES
                    (:reference, :action, :status, :request_body, :response_body, :error_message, :attempt)
            ');

            $stmt->execute([
                ':reference' => $reference,
                ':action' => $action,
                ':status' => $status,
                ':request_body' => $request ? json_encode($request) : null,
                ':response_body' => $response ? json_encode($response) : null,
                ':error_message' => $errorMessage,
                ':attempt' => $attempt,
            ]);
        } catch (\Throwable $e) {
            // Never let logging failures disrupt the payment flow.
            // Fall back to PHP error_log.
            error_log(sprintf(
                '[PaymentLog] Failed to write log: %s | ref=%s action=%s status=%s attempt=%d error=%s',
                $e->getMessage(),
                $reference,
                $action,
                $status,
                $attempt,
                $errorMessage ?? 'none'
            ));
        }
    }

    /**
     * Retrieve logs for a specific transaction reference.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getByReference(string $reference): array
    {
        $db = getDatabase();
        $stmt = $db->prepare('
            SELECT * FROM payment_logs
            WHERE reference = :reference
            ORDER BY created_at ASC
        ');
        $stmt->execute([':reference' => $reference]);

        return $stmt->fetchAll();
    }
}
