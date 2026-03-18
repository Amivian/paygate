<?php

/**
 * GET /api/verify?reference=xxx
 *
 * Verify a transaction with Paystack.
 *
 * Idempotency:
 *   - If the transaction is already verified (status != pending),
 *     return the cached result without calling Paystack again.
 *
 * Retry:
 *   - The PaystackClient handles retries internally.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/PaystackClient.php';
require_once __DIR__ . '/../lib/Validator.php';
require_once __DIR__ . '/../lib/Response.php';

// ── Validate reference ──────────────────────────────────────────────
$reference = $_GET['reference'] ?? '';

if (empty($reference)) {
    Response::error('Transaction reference is required', 400);
}

// ── Check local DB first ────────────────────────────────────────────
$db = getDatabase();
$stmt = $db->prepare('SELECT * FROM transactions WHERE reference = :ref LIMIT 1');
$stmt->execute([':ref' => $reference]);
$transaction = $stmt->fetch();

if (!$transaction) {
    Response::error('Transaction not found', 404);
}

// Idempotent: if already verified, return cached result
if ($transaction['status'] !== 'pending') {
    Response::success([
        'reference' => $transaction['reference'],
        'status' => $transaction['status'],
        'amount' => $transaction['amount'],
        'currency' => $transaction['currency'],
        'email' => $transaction['email'],
        'full_name' => $transaction['full_name'],
        'channel' => $transaction['channel'],
        'paid_at' => $transaction['paid_at'],
        'paystack_id' => $transaction['paystack_id'],
    ], 'Transaction already verified (cached)');
}

// ── Call Paystack Verify API ────────────────────────────────────────
try {
    $paystack = new PaystackClient();
    $response = $paystack->verifyTransaction($reference);
    $data = $response['data'] ?? [];

    // Map Paystack status to our status enum
    $status = match ($data['status'] ?? 'unknown') {
        'success' => 'success',
        'failed' => 'failed',
        'abandoned' => 'abandoned',
        default => 'pending',
    };

    // Update transaction in DB
    $stmt = $db->prepare('
        UPDATE transactions
        SET status      = :status,
            paystack_id = :paystack_id,
            channel     = :channel,
            paid_at     = :paid_at,
            metadata    = :metadata
        WHERE reference = :reference
    ');
    $paidAt = !empty($data['paid_at']) ? date('Y-m-d H:i:s', strtotime($data['paid_at'])) : null;

    $stmt->execute([
        ':status' => $status,
        ':paystack_id' => $data['id'] ?? null,
        ':channel' => $data['channel'] ?? null,
        ':paid_at' => $paidAt,
        ':metadata' => isset($data['metadata']) ? json_encode($data['metadata']) : null,
        ':reference' => $reference,
    ]);

    Response::success([
        'reference' => $reference,
        'status' => $status,
        'amount' => $data['amount'] ?? $transaction['amount'],
        'currency' => $data['currency'] ?? $transaction['currency'],
        'email' => $data['customer']['email'] ?? $transaction['email'],
        'full_name' => $transaction['full_name'],
        'channel' => $data['channel'] ?? null,
        'paid_at' => $data['paid_at'] ?? null,
        'paystack_id' => $data['id'] ?? null,
    ], 'Transaction verified successfully');

} catch (RuntimeException $e) {
    Response::error('Verification failed: ' . $e->getMessage(), 502);
}
