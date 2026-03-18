<?php

/**
 * GET /api/transactions
 *
 * List transactions from the local database with pagination
 * and optional status filtering.
 *
 * Query params:
 *   page    int    (default: 1)
 *   limit   int    (default: 20, max: 100)
 *   status  string (pending, success, failed, abandoned)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/Response.php';

// ── Parse query parameters ──────────────────────────────────────────
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = min(100, max(1, (int) ($_GET['limit'] ?? 20)));
$status = $_GET['status'] ?? null;
$offset = ($page - 1) * $limit;

$db = getDatabase();

// ── Build query ─────────────────────────────────────────────────────
$where = '';
$params = [];

$validStatuses = ['pending', 'success', 'failed', 'abandoned'];
if ($status && in_array($status, $validStatuses, true)) {
    $where = 'WHERE status = :status';
    $params[':status'] = $status;
}

// Count total for pagination
$countSql = "SELECT COUNT(*) as total FROM transactions {$where}";
$countStmt = $db->prepare($countSql);
$countStmt->execute($params);
$total = (int) $countStmt->fetch()['total'];
$totalPages = max(1, (int) ceil($total / $limit));

// Fetch page
$dataSql = "SELECT id, reference, email, full_name, amount, currency, status, channel, paid_at, created_at
             FROM transactions {$where}
             ORDER BY created_at DESC
             LIMIT :limit OFFSET :offset";
$dataStmt = $db->prepare($dataSql);

// Bind pagination params separately (PDO requires it for LIMIT/OFFSET)
foreach ($params as $key => $value) {
    $dataStmt->bindValue($key, $value);
}
$dataStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$dataStmt->execute();

$transactions = $dataStmt->fetchAll();

// ── Format amounts for display ──────────────────────────────────────
$formatted = array_map(function (array $tx): array {
    $tx['amount_formatted'] = '₦' . number_format($tx['amount'] / 100, 2);
    return $tx;
}, $transactions);

// ── Response ────────────────────────────────────────────────────────
Response::success([
    'transactions' => $formatted,
    'pagination' => [
        'page' => $page,
        'limit' => $limit,
        'total' => $total,
        'total_pages' => $totalPages,
    ],
], 'Transactions retrieved');
