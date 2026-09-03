<?php
/**
 * UpMizik - Security & Audit Logs API Endpoint (Hostinger / MySQL)
 * 
 * Sèvi ak: blocages_securite, tentatives_connexion, logs_activite
 */

require_once dirname(__DIR__) . '/middleware/cors.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/middleware/auth.php';

$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

// ----------------------------------------------------------
// GET: Lis IP bloke, Tantativ koneksyon, ak Logs aktivite
// ----------------------------------------------------------
if ($method === 'GET') {
    $action = $_GET['action'] ?? 'all';

    $result = [
        'success' => true,
        'blocked_ips' => [],
        'login_attempts' => [],
        'activity_logs' => []
    ];

    if ($action === 'blocked_ips' || $action === 'all') {
        $stmt = $pdo->query("
            SELECT 
                id,
                ip,
                motif AS reason,
                date_blocage AS blocked_at,
                expire_a AS expires_at 
            FROM blocages_securite 
            WHERE expire_a > NOW() 
            ORDER BY date_blocage DESC
        ");
        $result['blocked_ips'] = $stmt->fetchAll();
    }

    if ($action === 'login_attempts' || $action === 'all') {
        $stmt = $pdo->query("
            SELECT 
                id,
                identifiant AS identifier,
                ip,
                reussi AS success,
                date_tentative AS timestamp 
            FROM tentatives_connexion 
            ORDER BY date_tentative DESC 
            LIMIT 50
        ");
        $attempts = $stmt->fetchAll();
        foreach ($attempts as &$att) {
            $att['success'] = (bool)$att['success'];
        }
        $result['login_attempts'] = $attempts;
    }

    if ($action === 'activity_logs' || $action === 'all') {
        $stmt = $pdo->query("
            SELECT 
                id,
                utilisateur_id AS userId,
                nom_utilisateur AS userName,
                action,
                details,
                ip,
                date_creation AS timestamp 
            FROM logs_activite 
            ORDER BY date_creation DESC 
            LIMIT 100
        ");
        $result['activity_logs'] = $stmt->fetchAll();
    }

    jsonResponse($result);
}

// ----------------------------------------------------------
// POST: Bloke / Debloke IP, oswa Anrejistre yon Log
// ----------------------------------------------------------
if ($method === 'POST') {
    $data = getJsonInput();
    $action = $data['action'] ?? '';

    // 1. Bloke yon adrès IP
    if ($action === 'block_ip') {
        $ip = trim($data['ip'] ?? '');
        $reason = trim($data['reason'] ?? 'Bloke manyèlman pa admin');
        $minutes = (int)($data['minutes'] ?? 1440); // 24 èdtan pa defo

        if (empty($ip)) {
            jsonResponse(['success' => false, 'message' => 'IP obligatwa.'], 400);
        }

        $stmt = $pdo->prepare("
            INSERT INTO blocages_securite (ip, motif, expire_a) 
            VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))
            ON DUPLICATE KEY UPDATE motif = VALUES(motif), expire_a = VALUES(expire_a)
        ");
        $stmt->execute([$ip, $reason, $minutes]);

        jsonResponse(['success' => true, 'message' => "Adrès IP $ip bloke avèk siksè."]);
    }

    // 2. Debloke yon adrès IP
    if ($action === 'unblock_ip') {
        $ip = trim($data['ip'] ?? '');
        if (empty($ip)) {
            jsonResponse(['success' => false, 'message' => 'IP obligatwa.'], 400);
        }

        $stmt = $pdo->prepare("DELETE FROM blocages_securite WHERE ip = ?");
        $stmt->execute([$ip]);

        jsonResponse(['success' => true, 'message' => "Adrès IP $ip debloke avèk siksè."]);
    }

    // 3. Anrejistre yon log aktivite
    if ($action === 'log_activity') {
        $userId = $data['userId'] ?? null;
        $userName = $data['userName'] ?? 'Sistèm';
        $logAction = $data['logAction'] ?? 'action';
        $details = $data['details'] ?? '';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        $stmt = $pdo->prepare("
            INSERT INTO logs_activite (utilisateur_id, nom_utilisateur, action, details, ip)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $userName, $logAction, $details, $ip]);

        jsonResponse(['success' => true, 'message' => 'Log anrejistre.']);
    }

    jsonResponse(['success' => false, 'message' => 'Aksyon pa rekonèt.'], 400);
}

jsonResponse(['success' => false, 'message' => 'Metòd sa a pa sipòte.'], 405);
