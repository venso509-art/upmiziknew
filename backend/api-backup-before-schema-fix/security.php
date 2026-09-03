<?php
/**
 * UpMizik - Security & Intrusion Logs API Endpoint (Hostinger / MySQL)
 */

require_once __DIR__ . '/../config/db.php';

$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

// GET: Rekipere tout rapò sekirite yo
if ($method === 'GET') {
    $action = $_GET['action'] ?? '';
    if ($action === 'clear' || $action === 'clear_all') {
        $pdo->exec("DELETE FROM security_logs");
        jsonResponse(['success' => true, 'message' => 'Tout rapò sekirite yo efase.']);
    }

    $stmt = $pdo->query("SELECT * FROM security_logs ORDER BY timestamp DESC LIMIT 100");
    $logs = $stmt->fetchAll();
    jsonResponse(['success' => true, 'logs' => $logs]);
}

// DELETE: Efase yon rapò oswa tout rapò yo
if ($method === 'DELETE') {
    $id = $_GET['id'] ?? null;
    if (!empty($id)) {
        $stmt = $pdo->prepare("DELETE FROM security_logs WHERE id = ?");
        $stmt->execute([$id]);
        jsonResponse(['success' => true, 'message' => 'Rapò sekirite efase avèk siksè.']);
    } else {
        $pdo->exec("DELETE FROM security_logs");
        jsonResponse(['success' => true, 'message' => 'Tout rapò sekirite yo efase nèt.']);
    }
}

// POST: Anrejistre yon tantativ koneksyon sispèk oswa egzekite aksyon netwayaj
if ($method === 'POST') {
    $action = $_GET['action'] ?? '';
    if ($action === 'clear' || $action === 'clear_activity_logs') {
        try {
            $pdo->exec("DELETE FROM security_logs");
            $pdo->exec("DELETE FROM activity_logs");
        } catch (Exception $e) {
            // If table does not exist or already clear
        }
        jsonResponse(['success' => true, 'message' => 'Tout jounal aktivite ak rapò sekirite yo efase nèt.']);
    }

    $data = getJsonInput();
    
    $id = !empty($data['id']) ? $data['id'] : 'sec_' . time() . '_' . bin2hex(random_bytes(2));
    $attemptedEmail = $data['attemptedEmail'] ?? 'enkoni';
    $attemptCount = (int)($data['attemptCount'] ?? 1);
    $stage = in_array($data['stage'] ?? 'primary_login', ['primary_login', 'master_key']) ? $data['stage'] : 'primary_login';
    $photoUrl = $data['photoUrl'] ?? null;
    $userAgent = $data['userAgent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown UserAgent');
    $ipPlaceholder = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $status = $data['status'] ?? 'alert';
    $officialEmail = 'upmizik@gmail.com';
    $notes = $data['notes'] ?? "🚨 Alèt tantativ koneksyon echwe voye bay imèl ofisyèl $officialEmail.";
    $unlockToken = $data['unlockToken'] ?? bin2hex(random_bytes(16));

    $stmt = $pdo->prepare("
        INSERT INTO security_logs (id, attemptedEmail, attemptCount, stage, photoUrl, userAgent, ipPlaceholder, status, notes, unlockToken)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$id, $attemptedEmail, $attemptCount, $stage, $photoUrl, $userAgent, $ipPlaceholder, $status, $notes, $unlockToken]);

    jsonResponse([
        'success' => true, 
        'message' => 'Alèt sekirite anrejistre epi voye bay ' . $officialEmail, 
        'logId' => $id,
        'officialEmail' => $officialEmail
    ]);
}
