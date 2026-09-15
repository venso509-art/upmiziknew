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

function mapStatusToDb(string $status): string {
    return match (strtolower($status)) {
        'pending' => 'en_attente',
        'validated' => 'valide',
        'rejected' => 'rejete',
        default => $status
    };
}

function mapStatusToFrontend(string $status): string {
    return match (strtolower($status)) {
        'en_attente' => 'pending',
        'valide' => 'validated',
        'rejete' => 'rejected',
        default => $status
    };
}

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
                $upStmt = $pdo->prepare("UPDATE dons SET statut = 'valide' WHERE id = ?");
                $upStmt->execute([$orderId]);

                // Mete ajou total nan musiques ak artistes
                $getDon = $pdo->prepare("SELECT musique_id, artiste_id, montant, part_artiste FROM dons WHERE id = ?");
                $getDon->execute([$orderId]);
                $d = $getDon->fetch();
                if ($d) {
                    $pdo->prepare("UPDATE musiques SET total_dons = total_dons + ? WHERE id = ?")
                        ->execute([$d['montant'], $d['musique_id']]);
                    $pdo->prepare("UPDATE artistes SET total_dons_recus = total_dons_recus + ? WHERE id = ?")
                        ->execute([$d['part_artiste'], $d['artiste_id']]);
                }
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

    $query = "
        SELECT 
            id,
            musique_id AS musicId,
            titre_musique AS musicTitle,
            artiste_id AS artistId,
            nom_artiste AS artistName,
            montant AS amount,
            devise AS currency,
            nom_donateur AS donorName,
            telephone_donateur AS donorPhone,
            preuve_url AS proofUrl,
            methode_paiement AS paymentMethod,
            statut AS status,
            part_artiste AS artistShare,
            part_plateforme AS platformShare,
            date_don AS created_at
        FROM dons 
        WHERE 1=1
    ";
    $params = [];

    if ($artistId) {
        $query .= " AND artiste_id = ?";
        $params[] = $artistId;
    }
    if ($musicId) {
        $query .= " AND musique_id = ?";
        $params[] = $musicId;
    }
    if ($status && $status !== 'all') {
        $query .= " AND statut = ?";
        $params[] = mapStatusToDb($status);
    }

    $query .= " ORDER BY date_don DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $donations = $stmt->fetchAll();

    foreach ($donations as &$dn) {
        $dn['status'] = mapStatusToFrontend($dn['status'] ?? 'en_attente');
        $dn['amount'] = (float)($dn['amount'] ?? 0);
        $dn['artistShare'] = (float)($dn['artistShare'] ?? 0);
        $dn['platformShare'] = (float)($dn['platformShare'] ?? 0);
        $dn['createdAt'] = $dn['created_at'] ?? date('Y-m-d H:i:s');
        $dn['date'] = $dn['createdAt'];
    }

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
            // Anrejistre kòm en_attente nan baz done a
            $artistShare = $amount * 0.85;
            $platformShare = $amount * 0.15;

            $ins = $pdo->prepare("
                INSERT INTO dons (
                    id, musique_id, titre_musique, artiste_id, nom_artiste, montant,
                    devise, nom_donateur, telephone_donateur, preuve_url, methode_paiement,
                    statut, part_artiste, part_plateforme, date_don
                ) VALUES (
                    ?, ?, ?, ?, ?, ?,
                    'HTG', ?, ?, 'MonCash Online', 'MonCash',
                    'en_attente', ?, ?, NOW()
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
    $currency = $data['currency'] ?? 'HTG';
    $donorName = $data['donorName'] ?? 'Fanatik Anonim';
    $donorPhone = $data['donorPhone'] ?? 'Non espesifye';
    $proofUrl = $data['proofUrl'] ?? '';
    $paymentMethod = $data['paymentMethod'] ?? 'MonCash';
    $status = mapStatusToDb($data['status'] ?? 'pending');
    $artistShare = (float)($data['artistShare'] ?? ($amount * 0.85));
    $platformShare = (float)($data['platformShare'] ?? ($amount * 0.15));

    try {
        // Asire atis la egziste nan tab 'artistes' pou evite vyolasyon foreign key (fk_dons_artiste)
        $checkArtist = $pdo->prepare("SELECT id FROM artistes WHERE id = ?");
        $checkArtist->execute([$artistId]);
        if (!$checkArtist->fetch()) {
            $insArtist = $pdo->prepare("
                INSERT INTO artistes (id, nom_scene, nom_complet, email, telephone, ville, pin, statut, total_ecoutes, total_dons)
                VALUES (?, ?, ?, ?, 'N/A', 'Pòtoprens', '1234', 'actif', 0, 0)
                ON DUPLICATE KEY UPDATE nom_scene = VALUES(nom_scene)
            ");
            $insArtist->execute([
                $artistId,
                $artistName ?: 'Atis UpMizik',
                $artistName ?: 'Atis UpMizik',
                $artistId . '@upmizik.com'
            ]);
        }

        $stmt = $pdo->prepare("
            INSERT INTO dons (
                id, musique_id, titre_musique, artiste_id, nom_artiste, montant,
                devise, nom_donateur, telephone_donateur, preuve_url, methode_paiement,
                statut, part_artiste, part_plateforme, date_don
            ) VALUES (
                ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, NOW()
            )
            ON DUPLICATE KEY UPDATE
                statut = VALUES(statut),
                preuve_url = VALUES(preuve_url),
                nom_donateur = VALUES(nom_donateur),
                telephone_donateur = VALUES(telephone_donateur)
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
    } catch (Exception $e) {
        error_log("[donations.php] Erè anrejistreman donasyon: " . $e->getMessage());
        jsonResponse([
            'success' => false,
            'message' => 'Erè pandan anrejistreman donasyon: ' . $e->getMessage(),
            'data' => null,
            'errors' => [$e->getMessage()]
        ], 500);
    }
}

// ----------------------------------------------------------
// PUT / PATCH: Valide oswa Rejte Donasyon (Admin)
// ----------------------------------------------------------
if ($method === 'PUT' || $method === 'PATCH') {
    $data = getJsonInput();
    $id = $data['id'] ?? $_GET['id'] ?? null;
    $rawStatus = $data['status'] ?? null;

    if (!$id || !$rawStatus) {
        jsonResponse(['success' => false, 'message' => 'Id ak nouvo estati a obligatwa.'], 400);
    }

    $status = mapStatusToDb($rawStatus);

    $stmt = $pdo->prepare("UPDATE dons SET statut = ? WHERE id = ?");
    $stmt->execute([$status, $id]);

    // Si donasyon an valide, ogmante total_dons nan musiques ak artistes
    if ($status === 'valide') {
        $getDon = $pdo->prepare("SELECT musique_id, artiste_id, montant, part_artiste FROM dons WHERE id = ?");
        $getDon->execute([$id]);
        $don = $getDon->fetch();

        if ($don) {
            $pdo->prepare("UPDATE musiques SET total_dons = total_dons + ? WHERE id = ?")
                ->execute([$don['montant'], $don['musique_id']]);
            $pdo->prepare("UPDATE artistes SET total_dons_recus = total_dons_recus + ? WHERE id = ?")
                ->execute([$don['part_artiste'], $don['artiste_id']]);
        }
    }

    jsonResponse([
        'success' => true,
        'message' => 'Estati donasyon an mete ajou avèk siksè!',
        'data' => ['donationId' => $id, 'status' => mapStatusToFrontend($status)],
        'errors' => []
    ]);
}

jsonResponse(['success' => false, 'message' => 'Metòd sa a pa sipòte.'], 405);
