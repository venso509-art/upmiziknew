<?php
/**
 * UpMizik - Albums API Endpoint (Hostinger / MySQL / PDO)
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
            SELECT a.*, ar.name as artistLegalName, ar.stageName, ar.avatarUrl as artistAvatar 
            FROM albums a 
            LEFT JOIN artists ar ON a.artistId = ar.id 
            WHERE a.id = ?
        ");
        $stmt->execute([$id]);
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
            SELECT * FROM musics 
            WHERE album_id = ? OR albumName = ?
            ORDER BY trackNumber ASC, created_at ASC
        ");
        $tracksStmt->execute([$id, $album['title']]);
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
            SELECT a.*, ar.stageName, ar.avatarUrl as artistAvatar,
                   (SELECT COUNT(*) FROM musics m WHERE m.album_id = a.id OR m.albumName = a.title) as tracksCount
            FROM albums a
            LEFT JOIN artists ar ON a.artistId = ar.id
            WHERE 1=1
        ";
        $params = [];

        if ($artistId) {
            $query .= " AND a.artistId = ?";
            $params[] = $artistId;
        }

        if ($status && $status !== 'all') {
            $query .= " AND a.status = ?";
            $params[] = $status;
        }

        $query .= " ORDER BY a.releaseDate DESC, a.created_at DESC";
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
// POST: Kreye yon nouvo Albòm
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

    $id = !empty($data['id']) ? $data['id'] : 'alb_' . time() . '_' . bin2hex(random_bytes(3));
    $title = trim($data['title']);
    $artistId = $data['artistId'];
    $artistName = $data['artistName'] ?? 'Atis UpMizik';
    $coverUrl = $data['coverUrl'] ?? 'https://images.unsplash.com/photo-1514525253161-7a46d19cd819?w=500&auto=format&fit=crop&q=80';
    $description = $data['description'] ?? null;
    $genre = $data['genre'] ?? $data['category'] ?? 'Tout';
    $releaseDate = $data['releaseDate'] ?? date('Y-m-d');
    $status = $data['status'] ?? 'active';

    $stmt = $pdo->prepare("
        INSERT INTO albums (id, title, artistId, artistName, coverUrl, description, genre, releaseDate, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            title = VALUES(title),
            artistName = VALUES(artistName),
            coverUrl = VALUES(coverUrl),
            description = VALUES(description),
            genre = VALUES(genre),
            releaseDate = VALUES(releaseDate),
            status = VALUES(status)
    ");

    $stmt->execute([$id, $title, $artistId, $artistName, $coverUrl, $description, $genre, $releaseDate, $status]);

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
// PUT / PATCH: Modifye yon Albòm
// ----------------------------------------------------------
if ($method === 'PUT' || $method === 'PATCH') {
    $data = getJsonInput();
    $id = $data['id'] ?? $_GET['id'] ?? null;

    if (!$id) {
        jsonResponse([
            'success' => false,
            'message' => 'Id albòm nan obligatwa pou modifikasyon.',
            'data' => null,
            'errors' => ['Missing album id']
        ], 400);
    }

    $fields = [];
    $params = [];

    $updatable = ['title', 'coverUrl', 'description', 'genre', 'releaseDate', 'status'];
    foreach ($updatable as $f) {
        if (isset($data[$f])) {
            $fields[] = "`$f` = ?";
            $params[] = $data[$f];
        }
    }

    if (empty($fields)) {
        jsonResponse(['success' => false, 'message' => 'Pa gen okenn done pou modifye.'], 400);
    }

    $params[] = $id;
    $sql = "UPDATE albums SET " . implode(', ', $fields) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    jsonResponse([
        'success' => true,
        'message' => 'Albòm nan mete ajou avèk siksè!',
        'data' => ['albumId' => $id],
        'errors' => []
    ]);
}

// ----------------------------------------------------------
// DELETE: Efase yon Albòm
// ----------------------------------------------------------
if ($method === 'DELETE') {
    $id = $_GET['id'] ?? getJsonInput()['id'] ?? null;

    if (!$id) {
        jsonResponse(['success' => false, 'message' => 'Id albòm nan obligatwa.'], 400);
    }

    $stmt = $pdo->prepare("DELETE FROM albums WHERE id = ?");
    $stmt->execute([$id]);

    jsonResponse([
        'success' => true,
        'message' => 'Albòm nan efase avèk siksè!',
        'data' => ['albumId' => $id],
        'errors' => []
    ]);
}

jsonResponse(['success' => false, 'message' => 'Metòd sa a pa sipòte.'], 405);
