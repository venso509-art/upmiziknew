<?php
/**
 * UpMizik - Albums API Endpoint (Derived from musiques table)
 */

require_once dirname(__DIR__) . '/middleware/cors.php';
require_once dirname(__DIR__) . '/config/database.php';

$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

// ----------------------------------------------------------
// GET: Lis Albòm oswa Detay yon Albòm ak Tracks
// ----------------------------------------------------------
if ($method === 'GET') {
    $id = $_GET['id'] ?? null;
    $artistId = $_GET['artistId'] ?? null;
    $status = $_GET['status'] ?? 'active';

    if ($id) {
        $stmt = $pdo->prepare("
            SELECT 
                COALESCE(m.nom_album, m.titre) AS id,
                COALESCE(m.nom_album, m.titre) AS title,
                m.artiste_id AS artistId,
                m.nom_artiste AS artistName,
                MIN(m.cover_url) AS coverUrl,
                m.categorie AS genre,
                DATE(MIN(m.date_creation)) AS releaseDate,
                'active' AS status,
                MIN(m.date_creation) AS created_at,
                ar.nom_complet AS artistLegalName,
                ar.nom_scene AS stageName,
                ar.avatar_url AS artistAvatar,
                COUNT(m.id) AS tracksCount
            FROM musiques m 
            LEFT JOIN artistes ar ON m.artiste_id = ar.id 
            WHERE (m.nom_album = ? OR m.id = ?)
            GROUP BY COALESCE(m.nom_album, m.titre), m.artiste_id, m.nom_artiste, m.categorie, ar.nom_complet, ar.nom_scene, ar.avatar_url
            LIMIT 1
        ");
        $stmt->execute([$id, $id]);
        $album = $stmt->fetch();

        if (!$album) {
            jsonResponse([
                'success' => false,
                'message' => 'Albòm nan pa jwenn.',
                'data' => null,
                'errors' => ['Album not found']
            ], 404);
        }

        // Rekipere tout mizik ki nan albòm nan
        $tracksStmt = $pdo->prepare("
            SELECT 
                m.id,
                m.titre AS title,
                m.artiste_id AS artistId,
                m.nom_artiste AS artistName,
                m.featuring AS feat,
                m.categorie AS category,
                m.format AS releaseFormat,
                m.nom_album AS albumName,
                m.nom_album AS album_id,
                m.numero_piste AS trackNumber,
                m.cover_url AS coverUrl,
                m.audio_url AS audioUrl,
                m.duree AS duration,
                m.ecoutes AS listens,
                m.total_dons AS totalDonations,
                m.position,
                m.youtube_url AS youtubeUrl,
                m.tiktok_url AS tiktokUrl,
                m.instagram_url AS instagramUrl,
                'active' AS status,
                m.date_creation AS created_at
            FROM musiques m
            WHERE (m.nom_album = ? OR m.id = ?)
            ORDER BY m.numero_piste ASC, m.date_creation ASC
        ");
        $tracksStmt->execute([$album['title'], $id]);
        $album['tracks'] = $tracksStmt->fetchAll();

        jsonResponse([
            'success' => true,
            'message' => 'Detay albòm rekipere avèk siksè.',
            'data' => [
                'album' => $album
            ],
            'errors' => []
        ]);
    } else {
        $query = "
            SELECT 
                m.nom_album AS id,
                m.nom_album AS title,
                m.artiste_id AS artistId,
                m.nom_artiste AS artistName,
                MIN(m.cover_url) AS coverUrl,
                m.categorie AS genre,
                DATE(MIN(m.date_creation)) AS releaseDate,
                'active' AS status,
                MIN(m.date_creation) AS created_at,
                ar.nom_scene AS stageName,
                ar.avatar_url AS artistAvatar,
                COUNT(m.id) AS tracksCount
            FROM musiques m
            LEFT JOIN artistes ar ON m.artiste_id = ar.id
            WHERE m.nom_album IS NOT NULL AND m.nom_album != ''
        ";
        $params = [];

        if ($artistId) {
            $query .= " AND m.artiste_id = ?";
            $params[] = $artistId;
        }

        $query .= " GROUP BY m.nom_album, m.artiste_id, m.nom_artiste, m.categorie, ar.nom_scene, ar.avatar_url";
        $query .= " ORDER BY releaseDate DESC, created_at DESC";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $albums = $stmt->fetchAll();

        jsonResponse([
            'success' => true,
            'message' => 'Lis albòm rekipere.',
            'data' => [
                'albums' => $albums,
                'count' => count($albums)
            ],
            'errors' => []
        ]);
    }
}

// ----------------------------------------------------------
// POST: Kreye oswa Atribye Mizik nan yon Albòm
// ----------------------------------------------------------
if ($method === 'POST') {
    $data = getJsonInput();

    if (empty($data['title']) || empty($data['artistId'])) {
        jsonResponse([
            'success' => false,
            'message' => 'Tit albòm nan ak Id atis la obligatwa.',
            'data' => null,
            'errors' => ['Missing required fields: title, artistId']
        ], 400);
    }

    $id = !empty($data['id']) ? $data['id'] : ('alb_' . time() . '_' . bin2hex(random_bytes(2)));
    $title = trim($data['title']);
    $artistId = $data['artistId'];
    $trackIds = $data['trackIds'] ?? [];

    if (!empty($trackIds) && is_array($trackIds)) {
        $in = implode(',', array_fill(0, count($trackIds), '?'));
        $updateStmt = $pdo->prepare("UPDATE musiques SET nom_album = ?, format = 'album' WHERE id IN ($in)");
        $updateStmt->execute(array_merge([$title], $trackIds));
    }

    jsonResponse([
        'success' => true,
        'message' => 'Albòm nan anrejistre avèk siksè!',
        'data' => [
            'albumId' => $id,
            'title' => $title
        ],
        'errors' => []
    ], 201);
}

// ----------------------------------------------------------
// PUT / PATCH: Modifye Tit Albòm
// ----------------------------------------------------------
if ($method === 'PUT' || $method === 'PATCH') {
    $data = getJsonInput();
    $id = $data['id'] ?? $_GET['id'] ?? null;
    $newTitle = $data['title'] ?? null;

    if (!$id || !$newTitle) {
        jsonResponse([
            'success' => false,
            'message' => 'Id ak nouvo tit albòm nan obligatwa.',
            'data' => null,
            'errors' => ['Missing album id or title']
        ], 400);
    }

    $stmt = $pdo->prepare("UPDATE musiques SET nom_album = ? WHERE nom_album = ?");
    $stmt->execute([$newTitle, $id]);

    jsonResponse([
        'success' => true,
        'message' => 'Albòm nan mete ajou avèk siksè!',
        'data' => ['albumId' => $id],
        'errors' => []
    ]);
}

// ----------------------------------------------------------
// DELETE: Efase yon Albòm (Retire asosyasyon nan mizik yo)
// ----------------------------------------------------------
if ($method === 'DELETE') {
    $id = $_GET['id'] ?? getJsonInput()['id'] ?? null;

    if (!$id) {
        jsonResponse(['success' => false, 'message' => 'Id albòm nan obligatwa.'], 400);
    }

    $stmt = $pdo->prepare("UPDATE musiques SET nom_album = NULL, format = 'single' WHERE nom_album = ?");
    $stmt->execute([$id]);

    jsonResponse([
        'success' => true,
        'message' => 'Albòm nan efase avèk siksè!',
        'data' => ['albumId' => $id],
        'errors' => []
    ]);
}

jsonResponse(['success' => false, 'message' => 'Metòd sa a pa sipòte.'], 405);
