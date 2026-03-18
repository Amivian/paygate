<?php

/**
 * POST /api/webhook
 *
 * Paystack Webhook Handler
 *
 * Security:
 *   - Verifies HMAC SHA-512 signature against x-paystack-signature header
 *   - Rejects unverified payloads
 *
 * Idempotency:
 *   - Checks webhook_processed flag before processing
 *   - Safe to receive the same event multiple times
 *
 * Always returns 200 to acknowledge receipt (prevents Paystack retries).
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/PaystackClient.php';
require_once __DIR__ . '/../lib/Logger.php';
require_once __DIR__ . '/../lib/Response.php';

// ── Read raw payload ────────────────────────────────────────────────
$rawBody = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';

// ── Verify signature ────────────────────────────────────────────────
if (empty($signature) || !PaystackClient::verifyWebhookSignature($rawBody, $signature)) {
    Logger::log('webhook', 'webhook', 'failed', null, null, 'Invalid webhook signature');
    // Return 200 anyway to prevent Paystack from retrying an invalid signature
    // (this could be an attacker or a misconfiguration)
    Response::success(null, 'Webhook received');
}

// ── Parse event ─────────────────────────────────────────────────────
$event = json_decode($rawBody, true);

if (!is_array($event)) {
    Logger::log('webhook', 'webhook', 'failed', null, null, 'Invalid JSON in webhook body');
    Response::success(null, 'Webhook received');
}

$eventType = $event['event'] ?? 'unknown';
$data = $event['data'] ?? [];
$reference = $data['reference'] ?? 'unknown';

Logger::log($reference, 'webhook', 'success', null, $event, "Event: {$eventType}");

// ── Handle supported events ─────────────────────────────────────────
$db = getDatabase();

switch ($eventType) {
    case 'charge.success':
        processChargeSuccess($db, $data, $reference);
        break;

    case 'charge.failed':
        processChargeFailed($db, $data, $reference);
        break;

    default:
        // Log but don't process unsupported events
        Logger::log($reference, 'webhook', 'success', null, null, "Unhandled event type: {$eventType}");
        break;
}

Response::success(null, 'Webhook processed');

// ─── Event Handlers ─────────────────────────────────────────────────

function processChargeSuccess(PDO $db, array $data, string $reference): void
{
    // Idempotency check: skip if already processed
    $stmt = $db->prepare('SELECT webhook_processed FROM transactions WHERE reference = :ref LIMIT 1');
    $stmt->execute([':ref' => $reference]);
    $tx = $stmt->fetch();

    if (!$tx) {
        Logger::log($reference, 'webhook', 'failed', null, null, 'Transaction not found in DB');
        return;
    }

    if ((int) $tx['webhook_processed'] === 1) {
        Logger::log($reference, 'webhook', 'success', null, null, 'Already processed — idempotent skip');
        return;
    }

    // Update transaction
    $stmt = $db->prepare('
        UPDATE transactions
        SET status            = "success",
            paystack_id       = :paystack_id,
            channel           = :channel,
            paid_at           = :paid_at,
            metadata          = :metadata,
            webhook_processed = 1
        WHERE reference = :reference
    ');
    $paidAt = !empty($data['paid_at']) ? date('Y-m-d H:i:s', strtotime($data['paid_at'])) : null;

    $stmt->execute([
        ':paystack_id' => $data['id'] ?? null,
        ':channel' => $data['channel'] ?? null,
        ':paid_at' => $paidAt,
        ':metadata' => isset($data['metadata']) ? json_encode($data['metadata']) : null,
        ':reference' => $reference,
    ]);

    Logger::log($reference, 'webhook', 'success', null, null, 'Transaction marked as SUCCESS via webhook');
}

function processChargeFailed(PDO $db, array $data, string $reference): void
{
    $stmt = $db->prepare('SELECT webhook_processed FROM transactions WHERE reference = :ref LIMIT 1');
    $stmt->execute([':ref' => $reference]);
    $tx = $stmt->fetch();

    if (!$tx) {
        Logger::log($reference, 'webhook', 'failed', null, null, 'Transaction not found in DB');
        return;
    }

    if ((int) $tx['webhook_processed'] === 1) {
        Logger::log($reference, 'webhook', 'success', null, null, 'Already processed — idempotent skip');
        return;
    }

    $stmt = $db->prepare('
        UPDATE transactions
        SET status            = "failed",
            webhook_processed = 1
        WHERE reference = :reference
    ');
    $stmt->execute([':reference' => $reference]);

    Logger::log($reference, 'webhook', 'success', null, null, 'Transaction marked as FAILED via webhook');
}
