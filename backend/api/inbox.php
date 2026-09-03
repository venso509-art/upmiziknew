<?php
/**
 * UpMizik - Artist Inbox API Endpoint (Hostinger / MySQL / messages_inbox)
 */

require_once dirname(__DIR__) . '/middleware/cors.php';
require_once dirname(__DIR__) . '/config/database.php';

$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

// ----------------------------------------------------------
// GET: Rekipere tout mesaj bwat yon atis
// ----------------------------------------------------------
if ($method === 'GET') {
    $artistId = $_GET['artistId'] ?? null;
    if (!$artistId) {
        jsonResponse(['success' => false, 'message' => 'artistId obligatwa.'], 400);
    }

    $stmt = $pdo->prepare("
        SELECT 
            id,
            artiste_id AS artistId,
            titre AS subject,
            message AS bodyText,
            message AS previewText,
            expediteur AS senderName,
            expediteur AS senderEmail,
            est_lu AS isRead,
            0 AS isStarred,
            type,
            date_envoi AS receivedAt
        FROM messages_inbox 
        WHERE artiste_id = ? 
        ORDER BY date_envoi DESC
    ");
    $stmt->execute([$artistId]);
    $messages = $stmt->fetchAll();

    foreach ($messages as &$msg) {
        $msg['isRead'] = (bool)$msg['isRead'];
        $msg['isStarred'] = false;
        $msg['previewText'] = mb_substr(strip_tags($msg['bodyText']), 0, 80);
    }

    jsonResponse(['success' => true, 'messages' => $messages, 'count' => count($messages)]);
}

// ----------------------------------------------------------
// POST: Voye yon nouvo mesaj nan bwat yon atis
// ----------------------------------------------------------
if ($method === 'POST') {
    $data = getJsonInput();
    if (empty($data['artistId']) || empty($data['subject'])) {
        jsonResponse(['success' => false, 'message' => 'artistId ak subject obligatwa.'], 400);
    }

    $id = !empty($data['id']) ? $data['id'] : ('msg_' . time() . '_' . bin2hex(random_bytes(3)));
    $artistId = $data['artistId'];
    $subject = trim($data['subject']);
    $bodyText = $data['bodyText'] ?? $data['message'] ?? $data['previewText'] ?? '';
    $senderName = $data['senderName'] ?? 'Ekip UpMizik';
    $type = $data['type'] ?? 'info';

    $stmt = $pdo->prepare("
        INSERT INTO messages_inbox (
            id, artiste_id, titre, message, expediteur, est_lu, type, date_envoi
        ) VALUES (?, ?, ?, ?, ?, 0, ?, NOW())
    ");

    $stmt->execute([
        $id, $artistId, $subject, $bodyText, $senderName, $type
    ]);

    jsonResponse(['success' => true, 'message' => 'Mesaj voye avèk siksè.', 'messageId' => $id], 201);
}

// ----------------------------------------------------------
// PUT: Make mesaj kòm Li (isRead)
// ----------------------------------------------------------
if ($method === 'PUT') {
    $data = getJsonInput();
    $id = $data['id'] ?? $_GET['id'] ?? null;
    if (!$id) {
        jsonResponse(['success' => false, 'message' => 'ID mesaj la obligatwa.'], 400);
    }

    if (isset($data['isRead'])) {
        $stmt = $pdo->prepare("UPDATE messages_inbox SET est_lu = ? WHERE id = ?");
        $stmt->execute([$data['isRead'] ? 1 : 0, $id]);
    }

    jsonResponse(['success' => true, 'message' => 'Mesaj aktyalize.']);
}

// ----------------------------------------------------------
// DELETE: Efase yon mesaj
// ----------------------------------------------------------
if ($method === 'DELETE') {
    $id = $_GET['id'] ?? null;
    if (!$id) {
        jsonResponse(['success' => false, 'message' => 'ID mesaj la obligatwa.'], 400);
    }
    $stmt = $pdo->prepare("DELETE FROM messages_inbox WHERE id = ?");
    $stmt->execute([$id]);
    jsonResponse(['success' => true, 'message' => 'Mesaj efase avèk siksè.']);
}

jsonResponse(['success' => false, 'message' => 'Metòd sa a pa sipòte.'], 405);
