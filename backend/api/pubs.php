<?php
/**
 * UpMizik - Advertisements / Pubs API Endpoint (Hostinger / MySQL / publicites)
 */

require_once dirname(__DIR__) . '/middleware/cors.php';
require_once dirname(__DIR__) . '/config/database.php';

$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

// ----------------------------------------------------------
// GET: Lis piblisite oswa enkremente vues/clics
// ----------------------------------------------------------
if ($method === 'GET') {
    $action = $_GET['action'] ?? 'list';
    $id = $_GET['id'] ?? null;

    if ($action === 'click' && $id) {
        $stmt = $pdo->prepare("UPDATE publicites SET clics = clics + 1 WHERE id = ?");
        $stmt->execute([$id]);
        jsonResponse(['success' => true, 'message' => 'Klik enkremente.']);
    }

    if ($action === 'view' && $id) {
        $stmt = $pdo->prepare("UPDATE publicites SET vues = vues + 1 WHERE id = ?");
        $stmt->execute([$id]);
        jsonResponse(['success' => true, 'message' => 'Vi enkremente.']);
    }

    $activeOnly = isset($_GET['activeOnly']) ? filter_var($_GET['activeOnly'], FILTER_VALIDATE_BOOLEAN) : false;

    $query = "
        SELECT 
            id,
            titre AS title,
            description,
            image_url AS imageUrl,
            lien_url AS linkUrl,
            nom_sponsor AS sponsorName,
            actif AS active,
            clics AS clicks,
            vues AS views,
            date_debut AS startDate,
            date_fin AS endDate,
            date_creation AS created_at
        FROM publicites 
        WHERE 1=1
    ";
    $params = [];

    if ($activeOnly) {
        $query .= " AND actif = 1";
    }

    $query .= " ORDER BY date_creation DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $pubs = $stmt->fetchAll();

    foreach ($pubs as &$p) {
        $p['active'] = (bool)$p['active'];
    }

    jsonResponse(['success' => true, 'pubs' => $pubs, 'count' => count($pubs)]);
}

// ----------------------------------------------------------
// POST: Kreye yon nouvo piblisite
// ----------------------------------------------------------
if ($method === 'POST') {
    $data = getJsonInput();
    if (empty($data['title']) || empty($data['imageUrl'])) {
        jsonResponse(['success' => false, 'message' => 'Tit ak imaj piblisite obligatwa.'], 400);
    }

    $id = !empty($data['id']) ? $data['id'] : ('pub_' . time() . '_' . bin2hex(random_bytes(2)));
    $title = trim($data['title']);
    $description = $data['description'] ?? '';
    $imageUrl = $data['imageUrl'];
    $linkUrl = $data['linkUrl'] ?? '#';
    $sponsorName = $data['sponsorName'] ?? 'Sponsor UpMizik';
    $active = isset($data['active']) ? ($data['active'] ? 1 : 0) : 1;

    $stmt = $pdo->prepare("
        INSERT INTO publicites (
            id, titre, description, image_url, lien_url, nom_sponsor, actif, date_creation
        ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            titre = VALUES(titre),
            description = VALUES(description),
            image_url = VALUES(image_url),
            lien_url = VALUES(lien_url),
            nom_sponsor = VALUES(nom_sponsor),
            actif = VALUES(actif)
    ");

    $stmt->execute([
        $id, $title, $description, $imageUrl, $linkUrl, $sponsorName, $active
    ]);

    jsonResponse(['success' => true, 'message' => 'Piblisite anrejistre avèk siksè.', 'pubId' => $id], 201);
}

// ----------------------------------------------------------
// PUT / PATCH: Modifye yon piblisite
// ----------------------------------------------------------
if ($method === 'PUT' || $method === 'PATCH') {
    $data = getJsonInput();
    $id = $data['id'] ?? $_GET['id'] ?? null;
    if (!$id) {
        jsonResponse(['success' => false, 'message' => 'ID piblisite a obligatwa.'], 400);
    }

    $fieldMap = [
        'title' => 'titre',
        'description' => 'description',
        'imageUrl' => 'image_url',
        'linkUrl' => 'lien_url',
        'sponsorName' => 'nom_sponsor',
        'active' => 'actif'
    ];

    $fields = [];
    $params = [];

    foreach ($fieldMap as $frontendKey => $dbCol) {
        if (isset($data[$frontendKey])) {
            $val = $data[$frontendKey];
            if ($frontendKey === 'active') {
                $val = $val ? 1 : 0;
            }
            $fields[] = "`$dbCol` = ?";
            $params[] = $val;
        }
    }

    if (empty($fields)) {
        jsonResponse(['success' => false, 'message' => 'Pa gen done pou modifye.'], 400);
    }

    $params[] = $id;
    $sql = "UPDATE publicites SET " . implode(', ', $fields) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    jsonResponse(['success' => true, 'message' => 'Piblisite aktyalize avèk siksè.']);
}

// ----------------------------------------------------------
// DELETE: Efase yon piblisite
// ----------------------------------------------------------
if ($method === 'DELETE') {
    $id = $_GET['id'] ?? null;
    if (!$id) {
        jsonResponse(['success' => false, 'message' => 'ID piblisite a obligatwa.'], 400);
    }
    $stmt = $pdo->prepare("DELETE FROM publicites WHERE id = ?");
    $stmt->execute([$id]);
    jsonResponse(['success' => true, 'message' => 'Piblisite efase avèk siksè.']);
}

jsonResponse(['success' => false, 'message' => 'Metòd sa a pa sipòte.'], 405);
