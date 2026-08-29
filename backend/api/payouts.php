<?php
/**
 * UpMizik - Payouts & Transfers API Endpoint (Hostinger / MySQL / PDO)
 */

require_once dirname(__DIR__) . '/middleware/cors.php';
require_once dirname(__DIR__) . '/config/database.php';

$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

// ----------------------------------------------------------
// GET: Lis tout Payouts (Demann retrè lajan atis)
// ----------------------------------------------------------
if ($method === 'GET') {
    $artistId = $_GET['artistId'] ?? null;
    $status = $_GET['status'] ?? null;

    $query = "SELECT p.*, a.stageName, a.name as artistLegalName, a.phone as artistPhone, a.city as artistCity 
              FROM payouts p 
              LEFT JOIN artists a ON p.artistId = a.id 
              WHERE 1=1";
    $params = [];

    if ($artistId) {
        $query .= " AND p.artistId = ?";
        $params[] = $artistId;
    }

    if ($status && $status !== 'all') {
        $query .= " AND p.status = ?";
        $params[] = $status;
    }

    $query .= " ORDER BY p.requestedAt DESC, p.created_at DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $payouts = $stmt->fetchAll();

    jsonResponse([
        'success' => true,
        'message' => 'Lis payouts rekipere avèk siksè.',
        'data' => [
            'payouts' => $payouts,
            'count' => count($payouts)
        ],
        'payouts' => $payouts,
        'count' => count($payouts),
        'errors' => []
    ]);
}

// ----------------------------------------------------------
// POST: Kreye yon nouvo Demann Payout
// ----------------------------------------------------------
if ($method === 'POST') {
    $data = getJsonInput();

    if (empty($data['artistId']) || empty($data['amount'])) {
        jsonResponse([
            'success' => false,
            'message' => 'Atis ak montan obligatwa pou mande retrè.',
            'data' => null,
            'errors' => ['Missing artistId or amount']
        ], 400);
    }

    $id = !empty($data['id']) ? $data['id'] : 'pay_' . time() . '_' . bin2hex(random_bytes(3));
    $artistId = $data['artistId'];
    $artistName = $data['artistName'] ?? 'Atis UpMizik';
    $amount = (float)$data['amount'];
    $currency = $data['currency'] ?? 'HTG';
    $paymentMethod = $data['paymentMethod'] ?? 'MonCash';
    $accountNumber = $data['accountNumber'] ?? $data['phone'] ?? '';
    $status = $data['status'] ?? 'pending';
    $notes = $data['notes'] ?? null;

    $stmt = $pdo->prepare("
        INSERT INTO payouts (
            id, artistId, artistName, amount, currency, paymentMethod,
            accountNumber, status, notes, requestedAt, created_at
        ) VALUES (
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?, NOW(), NOW()
        )
        ON DUPLICATE KEY UPDATE
            status = VALUES(status),
            notes = VALUES(notes)
    ");

    $stmt->execute([
        $id, $artistId, $artistName, $amount, $currency, $paymentMethod,
        $accountNumber, $status, $notes
    ]);

    jsonResponse([
        'success' => true,
        'message' => 'Demann retrè anrejistre avèk siksè!',
        'data' => ['payoutId' => $id],
        'payoutId' => $id,
        'errors' => []
    ], 201);
}

// ----------------------------------------------------------
// PUT / PATCH: Mete ajou estati yon Payout (Admin)
// ----------------------------------------------------------
if ($method === 'PUT' || $method === 'PATCH') {
    $data = getJsonInput();
    $id = $data['id'] ?? $_GET['id'] ?? null;
    $status = $data['status'] ?? null;
    $transactionRef = $data['transactionReference'] ?? $data['reference'] ?? null;

    if (!$id || !$status) {
        jsonResponse(['success' => false, 'message' => 'Id ak nouvo estati a obligatwa.'], 400);
    }

    $stmt = $pdo->prepare("
        UPDATE payouts 
        SET status = ?, 
            transactionReference = COALESCE(?, transactionReference),
            processedAt = CASE WHEN ? IN ('approved', 'paid') THEN NOW() ELSE processedAt END
        WHERE id = ?
    ");
    $stmt->execute([$status, $transactionRef, $status, $id]);

    jsonResponse([
        'success' => true,
        'message' => 'Estati peman an mete ajou avèk siksè!',
        'data' => ['payoutId' => $id, 'status' => $status],
        'errors' => []
    ]);
}

jsonResponse(['success' => false, 'message' => 'Metòd sa a pa sipòte.'], 405);
