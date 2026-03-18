<?php

/**
 * POST /api/initialize
 *
 * Initialize a Paystack transaction.
 *
 * Idempotency:
 *   - Generates a unique reference (PAY-{timestamp}-{random})
 *   - If a pending transaction with the same email + amount exists
 *     within the last 5 minutes, returns the existing one instead
 *     of creating a duplicate.
 *
 * Retry:
 *   - The PaystackClient handles retries internally with
 *     exponential backoff.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/PaystackClient.php';
require_once __DIR__ . '/../lib/Validator.php';
require_once __DIR__ . '/../lib/Response.php';

// ── Parse and validate input ────────────────────────────────────────
$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input)) {
    Response::error('Invalid JSON payload', 400);
}

// Convert Naira to kobo if frontend sends Naira
if (isset($input['amount'])) {
    $input['amount'] = (int) ($input['amount'] * 100);
}

$errors = Validator::validatePaymentInit($input);
if (!empty($errors)) {
    Response::error('Validation failed', 422, $errors);
}

$email = Validator::sanitize($input['email']);
$amount = (int) $input['amount'];
$fullName = isset($input['full_name']) ? Validator::sanitize($input['full_name']) : null;
$currency = strtoupper($input['currency'] ?? 'NGN');

// ── Idempotency check ───────────────────────────────────────────────
// If there's already a pending transaction for the same email + amount
// created in the last 5 minutes, return it instead of creating a new one.
$db = getDatabase();

$stmt = $db->prepare('
    SELECT * FROM transactions
    WHERE email = :email
      AND amount = :amount
      AND status = "pending"
      AND created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ORDER BY created_at DESC
    LIMIT 1
');
$stmt->execute([':email' => $email, ':amount' => $amount]);
$existing = $stmt->fetch();

if ($existing && !empty($existing['authorization_url'])) {
    Response::success([
        'authorization_url' => $existing['authorization_url'],
        'access_code' => $existing['access_code'],
        'reference' => $existing['reference'],
        'is_duplicate' => true,
    ], 'Existing pending transaction returned (idempotent)');
}

// ── Generate unique reference ───────────────────────────────────────
$reference = 'PAY-' . (string) round(microtime(true) * 1000) . '-' . bin2hex(random_bytes(4));

// ── Persist transaction (status: pending) ───────────────────────────
$stmt = $db->prepare('
    INSERT INTO transactions (reference, email, full_name, amount, currency, status, ip_address)
    VALUES (:reference, :email, :full_name, :amount, :currency, "pending", :ip)
');
$stmt->execute([
    ':reference' => $reference,
    ':email' => $email,
    ':full_name' => $fullName,
    ':amount' => $amount,
    ':currency' => $currency,
    ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
]);

// ── Call Paystack Initialize API ────────────────────────────────────
try {
    $paystack = new PaystackClient();

    $callbackUrl = env('APP_URL', 'http://localhost:3000') . '/payment/callback';

    $response = $paystack->initializeTransaction([
        'email' => $email,
        'amount' => $amount,
        'reference' => $reference,
        'currency' => $currency,
        'callback_url' => $callbackUrl,
        'metadata' => json_encode([
            'full_name' => $fullName,
            'initiated_at' => date('c'),
        ]),
    ]);

    $data = $response['data'] ?? [];

    // Update transaction with Paystack response
    $stmt = $db->prepare('
        UPDATE transactions
        SET authorization_url = :auth_url,
            access_code = :access_code
        WHERE reference = :reference
    ');
    $stmt->execute([
        ':auth_url' => $data['authorization_url'] ?? null,
        ':access_code' => $data['access_code'] ?? null,
        ':reference' => $reference,
    ]);

    Response::success([
        'authorization_url' => $data['authorization_url'] ?? null,
        'access_code' => $data['access_code'] ?? null,
        'reference' => $reference,
    ], 'Transaction initialized successfully');

} catch (RuntimeException $e) {
    // Update transaction status to failed
    $stmt = $db->prepare('UPDATE transactions SET status = "failed" WHERE reference = :ref');
    $stmt->execute([':ref' => $reference]);

    Response::error('Failed to initialize payment: ' . $e->getMessage(), 502);
}
