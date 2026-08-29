<?php
/**
 * UpMizik - Donations API Endpoint & MonCash Gateway Integration
 */

require_once dirname(__DIR__) . '/middleware/cors.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/services/moncash.php';

$pdo = getDBConnection();
$moncash = new MonCashService();
$method = $_SERVER['REQUEST_METHOD'];

// ----------------------------------------------------------
// GET: Lis Donasyon oswa Verifye Estati MonCash
// ----------------------------------------------------------
if ($method === 'GET') {
    $action = $_GET['action'] ?? 'list';

    // 1. Verifye estati yon peman MonCash
    if ($action === 'verify' || $action === 'return') {
        $transactionId = $_GET['transactionId'] ?? $_GET['transaction_id'] ?? null;
        $orderId = $_GET['orderId'] ?? $_GET['order_id'] ?? null;

        if (!$transactionId && !$orderId) {
            jsonResponse([
                'success' => false,
                'message' => 'Transaction ID oswa Order ID obligatwa pou verifikasyon.',
                'data' => null,
                'errors' => ['Missing transaction identifier']
            ], 400);
        }

        $verification = $moncash->verifyPayment($transactionId ?? '', $orderId);

        if ($verification['success'] && ($verification['is_paid'] ?? false)) {
            // Mete ajou baz done a si gen yon donation korespondan
            if ($orderId) {
                $upStmt = $pdo->prepare("UPDATE donations SET status = 'validated' WHERE id = ?");
                $upStmt->execute([$orderId]);
            }
        }

        jsonResponse([
            'success' => $verification['success'],
            'message' => $verification['message'] ?? 'Verifikasyon fini',
            'data' => $verification,
            'errors' => []
        ]);
    }

    // 2. Lis donasyon
    $artistId = $_GET['artistId'] ?? null;
    $musicId = $_GET['musicId'] ?? null;
    $status = $_GET['status'] ?? null;

    $query = "SELECT * FROM donations WHERE 1=1";
    $params = [];

    if ($artistId) {
        $query .= " AND artistId = ?";
        $params[] = $artistId;
    }
    if ($musicId) {
        $query .= " AND musicId = ?";
        $params[] = $musicId;
    }
    if ($status && $status !== 'all') {
        $query .= " AND status = ?";
        $params[] = $status;
    }

    $query .= " ORDER BY created_at DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $donations = $stmt->fetchAll();

    jsonResponse([
        'success' => true,
        'message' => 'Lis donasyon rekipere.',
        'data' => ['donations' => $donations, 'count' => count($donations)],
        'donations' => $donations,
        'count' => count($donations),
        'errors' => []
    ]);
}

// ----------------------------------------------------------
// POST: Kreye yon nouvo Donasyon oswa MonCash Order
// ----------------------------------------------------------
if ($method === 'POST') {
    $data = getJsonInput();
    $action = $data['action'] ?? 'create';

    // 1. Inisye yon Peman MonCash
    if ($action === 'initiate_moncash') {
        $amount = (float)($data['amount'] ?? 0);
        $musicId = $data['musicId'] ?? 'general';
        $artistId = $data['artistId'] ?? 'general';
        $donorName = trim($data['donorName'] ?? 'Fanatik UpMizik');
        $donorPhone = trim($data['donorPhone'] ?? '');

        if ($amount <= 0) {
            jsonResponse(['success' => false, 'message' => 'Montan donasyon an dwe plis pase 0.'], 400);
        }

        $orderId = 'don_' . time() . '_' . bin2hex(random_bytes(3));
        $paymentResult = $moncash->createPayment($orderId, $amount, "Sipò UpMizik pou {$data['artistName']}");

        if ($paymentResult['success']) {
            // Anrejistre kòm pending nan baz done a
            $artistShare = $amount * 0.85;
            $platformShare = $amount * 0.15;

            $ins = $pdo->prepare("
                INSERT INTO donations (
                    id, musicId, musicTitle, artistId, artistName, amount,
                    currency, donorName, donorPhone, proofUrl, paymentMethod,
                    status, artistShare, platformShare, created_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?,
                    'HTG', ?, ?, 'MonCash Online', 'MonCash',
                    'pending', ?, ?, NOW()
                )
            ");
            $ins->execute([
                $orderId, $musicId, $data['musicTitle'] ?? 'Mizik UpMizik',
                $artistId, $data['artistName'] ?? 'Atis', $amount,
                $donorName, $donorPhone, $artistShare, $platformShare
            ]);
        }

        jsonResponse([
            'success' => $paymentResult['success'],
            'message' => $paymentResult['message'] ?? 'Inisyasyon peman fini',
            'data' => array_merge($paymentResult, ['orderId' => $orderId]),
            'errors' => []
        ]);
    }

    // 2. Anrejistre yon Donasyon Manyèl ak Prèv Transfè (MonCash / Natcash)
    if (empty($data['musicId']) || empty($data['artistId']) || empty($data['amount'])) {
        jsonResponse([
            'success' => false,
            'message' => 'Enfòmasyon sou mizik, atis ak montan obligatwa pou anrejistre yon donasyon.',
            'data' => null,
            'errors' => ['Missing required donation fields']
        ], 400);
    }

    $id = !empty($data['id']) ? $data['id'] : 'don_' . time() . '_' . bin2hex(random_bytes(3));
    $musicId = $data['musicId'];
    $musicTitle = $data['musicTitle'] ?? 'Mizik UpMizik';
    $artistId = $data['artistId'];
    $artistName = $data['artistName'] ?? 'Atis UpMizik';
    $amount = (float)$data['amount'];
    $currency = $data['currency'] ?? 'USD';
    $donorName = $data['donorName'] ?? 'Fanatik Anonim';
    $donorPhone = $data['donorPhone'] ?? 'Non espesifye';
    $proofUrl = $data['proofUrl'] ?? '';
    $paymentMethod = $data['paymentMethod'] ?? 'MonCash';
    $status = $data['status'] ?? 'pending';
    $artistShare = (float)($data['artistShare'] ?? ($amount * 0.85));
    $platformShare = (float)($data['platformShare'] ?? ($amount * 0.15));

    $stmt = $pdo->prepare("
        INSERT INTO donations (
            id, musicId, musicTitle, artistId, artistName, amount,
            currency, donorName, donorPhone, proofUrl, paymentMethod,
            status, artistShare, platformShare, created_at
        ) VALUES (
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, NOW()
        )
        ON DUPLICATE KEY UPDATE
            status = VALUES(status),
            proofUrl = VALUES(proofUrl)
    ");

    $stmt->execute([
        $id, $musicId, $musicTitle, $artistId, $artistName, $amount,
        $currency, $donorName, $donorPhone, $proofUrl, $paymentMethod,
        $status, $artistShare, $platformShare
    ]);

    jsonResponse([
        'success' => true,
        'message' => 'Donasyon an anrejistre avèk siksè nan baz done a (estati: pending)!',
        'data' => ['donationId' => $id],
        'donationId' => $id,
        'errors' => []
    ], 201);
}

// ----------------------------------------------------------
// PUT / PATCH: Valide oswa Rejte Donasyon (Admin)
// ----------------------------------------------------------
if ($method === 'PUT' || $method === 'PATCH') {
    $data = getJsonInput();
    $id = $data['id'] ?? $_GET['id'] ?? null;
    $status = $data['status'] ?? null;

    if (!$id || !$status) {
        jsonResponse(['success' => false, 'message' => 'Id ak nouvo estati a obligatwa.'], 400);
    }

    $stmt = $pdo->prepare("UPDATE donations SET status = ? WHERE id = ?");
    $stmt->execute([$status, $id]);

    // Si donasyon an valide, ogmante totalDonations nan tablo musics ak artists
    if ($status === 'validated') {
        $getDon = $pdo->prepare("SELECT * FROM donations WHERE id = ?");
        $getDon->execute([$id]);
        $don = $getDon->fetch();

        if ($don) {
            $pdo->prepare("UPDATE musics SET totalDonations = totalDonations + ? WHERE id = ?")
                ->execute([$don['amount'], $don['musicId']]);
            $pdo->prepare("UPDATE artists SET totalDonationsReceived = totalDonationsReceived + ? WHERE id = ?")
                ->execute([$don['artistShare'], $don['artistId']]);
        }
    }

    jsonResponse([
        'success' => true,
        'message' => 'Estati donasyon an mete ajou avèk siksè!',
        'data' => ['donationId' => $id, 'status' => $status],
        'errors' => []
    ]);
}

jsonResponse(['success' => false, 'message' => 'Metòd sa a pa sipòte.'], 405);
