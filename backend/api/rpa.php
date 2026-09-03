<?php
/**
 * UpMizik - RPA (Révélation & Pwojè Atis) API Endpoint (Stored in configurations table)
 */

require_once dirname(__DIR__) . '/middleware/cors.php';
require_once dirname(__DIR__) . '/config/database.php';

$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

function getRpaList(PDO $pdo): array {
    $stmt = $pdo->prepare("SELECT valeur FROM configurations WHERE cle = 'rpa_items'");
    $stmt->execute();
    $val = $stmt->fetchColumn();
    if ($val) {
        $decoded = json_decode($val, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }
    return [];
}

function saveRpaList(PDO $pdo, array $list): void {
    $json = json_encode(array_values($list), JSON_UNESCAPED_UNICODE);
    $stmt = $pdo->prepare("
        INSERT INTO configurations (cle, valeur, description)
        VALUES ('rpa_items', ?, 'Lis pwojè ak revelasyon atis (RPA)')
        ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)
    ");
    $stmt->execute([$json]);
}

// ----------------------------------------------------------
// GET: Rekipere lis RPA
// ----------------------------------------------------------
if ($method === 'GET') {
    $rpaList = getRpaList($pdo);
    jsonResponse(['success' => true, 'rpa' => $rpaList, 'count' => count($rpaList)]);
}

// ----------------------------------------------------------
// POST: Ajoute oswa modifye RPA
// ----------------------------------------------------------
if ($method === 'POST') {
    $data = getJsonInput();
    if (empty($data['title']) || empty($data['artistName'])) {
        jsonResponse(['success' => false, 'message' => 'Tit ak Non Atis obligatwa.'], 400);
    }

    $id = !empty($data['id']) ? $data['id'] : ('rpa_' . time() . '_' . bin2hex(random_bytes(2)));
    $newItem = [
        'id' => $id,
        'title' => trim($data['title']),
        'description' => $data['description'] ?? '',
        'artistName' => trim($data['artistName']),
        'imageUrl' => $data['imageUrl'] ?? 'https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?w=500&auto=format&fit=crop&q=80',
        'mediaUrl' => $data['mediaUrl'] ?? null,
        'mediaType' => in_array($data['mediaType'] ?? 'image', ['image', 'gif', 'video']) ? $data['mediaType'] : 'image',
        'socialLink' => $data['socialLink'] ?? '#',
        'youtubeUrl' => $data['youtubeUrl'] ?? null,
        'badgeText' => $data['badgeText'] ?? 'Révélation du mois',
        'created_at' => $data['created_at'] ?? date('Y-m-d H:i:s')
    ];

    $list = getRpaList($pdo);
    $found = false;
    foreach ($list as $key => $item) {
        if ($item['id'] === $id) {
            $list[$key] = array_merge($item, $newItem);
            $found = true;
            break;
        }
    }
    if (!$found) {
        array_unshift($list, $newItem);
    }

    saveRpaList($pdo, $list);
    jsonResponse(['success' => true, 'message' => 'RPA anrejistre avèk siksè.', 'rpaId' => $id], 201);
}

// ----------------------------------------------------------
// DELETE: Efase RPA
// ----------------------------------------------------------
if ($method === 'DELETE') {
    $id = $_GET['id'] ?? getJsonInput()['id'] ?? null;
    if (!$id) {
        jsonResponse(['success' => false, 'message' => 'ID obligatwa.'], 400);
    }

    $list = getRpaList($pdo);
    $filtered = array_filter($list, fn($item) => $item['id'] !== $id);
    saveRpaList($pdo, $filtered);

    jsonResponse(['success' => true, 'message' => 'RPA efase avèk siksè.']);
}

jsonResponse(['success' => false, 'message' => 'Metòd sa a pa sipòte.'], 405);
