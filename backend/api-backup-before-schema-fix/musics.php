<?php
/**
 * UpMizik - Musics API Endpoint (Hostinger / MySQL / PDO)
 */

require_once dirname(__DIR__) . '/middleware/cors.php';
require_once dirname(__DIR__) . '/config/database.php';

$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

// ----------------------------------------------------------
// GET: Rekipere tout mizik oswa filtre pa kategori / atis / albòm
// ----------------------------------------------------------
if ($method === 'GET') {
    $id = $_GET['id'] ?? null;
    $artistId = $_GET['artistId'] ?? null;
    $albumId = $_GET['albumId'] ?? null;
    $category = $_GET['category'] ?? null;
    $status = $_GET['status'] ?? null; // 'active', 'pending', 'rejected', 'all'
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : null;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

    if ($id) {
        $stmt = $pdo->prepare("
            SELECT m.*, a.avatarUrl as artistAvatar, a.stageName, a.city as artistCity 
            FROM musics m 
            LEFT JOIN artists a ON m.artistId = a.id 
            WHERE m.id = ?
        ");
        $stmt->execute([$id]);
        $music = $stmt->fetch();

        if ($music) {
            // Rekipere kredi yo (split sheets)
            $credStmt = $pdo->prepare("SELECT * FROM music_credits WHERE musicId = ?");
            $credStmt->execute([$id]);
            $music['credits'] = $credStmt->fetchAll();

            jsonResponse([
                'success' => true,
                'message' => 'Mizik rekipere avèk siksè.',
                'data' => ['music' => $music],
                'music' => $music,
                'errors' => []
            ]);
        } else {
            jsonResponse([
                'success' => false,
                'message' => 'Mizik la pa jwenn.',
                'data' => null,
                'errors' => ['Music not found']
            ], 404);
        }
    } else {
        $query = "SELECT m.*, a.avatarUrl as artistAvatar, a.stageName FROM musics m LEFT JOIN artists a ON m.artistId = a.id WHERE 1=1";
        $params = [];

        if ($artistId) {
            $query .= " AND m.artistId = ?";
            $params[] = $artistId;
        }

        if ($albumId) {
            $query .= " AND (m.album_id = ? OR m.albumName = ?)";
            $params[] = $albumId;
            $params[] = $albumId;
        }

        if ($category && $category !== 'Tout') {
            $query .= " AND m.category = ?";
            $params[] = $category;
        }

        if ($status && $status !== 'all') {
            $query .= " AND m.status = ?";
            $params[] = $status;
        } elseif (!$status) {
            // Pa defo pou itilizatè piblik, sèlman mizik aktif yo afiche
            $query .= " AND m.status = 'active'";
        }

        $query .= " ORDER BY m.listens DESC, m.created_at DESC";

        if ($limit !== null && $limit > 0) {
            $query .= " LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
        }

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $musics = $stmt->fetchAll();

        // Rekipere kredi pou chak mizik si genyen
        if (!empty($musics)) {
            $musicIds = array_column($musics, 'id');
            $inClause = implode(',', array_fill(0, count($musicIds), '?'));
            $creditsStmt = $pdo->prepare("SELECT * FROM music_credits WHERE musicId IN ($inClause)");
            $creditsStmt->execute($musicIds);
            $allCredits = $creditsStmt->fetchAll();

            $creditsMap = [];
            foreach ($allCredits as $cred) {
                $creditsMap[$cred['musicId']][] = $cred;
            }

            foreach ($musics as &$m) {
                $m['credits'] = $creditsMap[$m['id']] ?? [];
            }
        }

        jsonResponse([
            'success' => true,
            'message' => 'Lis mizik rekipere.',
            'data' => ['musics' => $musics, 'count' => count($musics)],
            'musics' => $musics,
            'count' => count($musics),
            'errors' => []
        ]);
    }
}

// ----------------------------------------------------------
// POST: Ajoute yon nouvo mizik oswa Ogmante play_count
// ----------------------------------------------------------
if ($method === 'POST') {
    $data = getJsonInput();
    $action = $data['action'] ?? $_GET['action'] ?? 'create';

    // 1. OGM уроk / OGM LISENS (Play Count Increment)
    if ($action === 'play' || $action === 'increment_play') {
        $musicId = $data['musicId'] ?? $data['id'] ?? null;
        if (!$musicId) {
            jsonResponse(['success' => false, 'message' => 'Id mizik la obligatwa.'], 400);
        }

        $upStmt = $pdo->prepare("UPDATE musics SET listens = listens + 1 WHERE id = ?");
        $upStmt->execute([$musicId]);

        // Rekipere artistId pou mete ajou totalListens atis la tou
        $artStmt = $pdo->prepare("SELECT artistId FROM musics WHERE id = ?");
        $artStmt->execute([$musicId]);
        $artId = $artStmt->fetchColumn();
        if ($artId) {
            $pdo->prepare("UPDATE artists SET totalListens = totalListens + 1 WHERE id = ?")->execute([$artId]);
        }

        jsonResponse([
            'success' => true,
            'message' => 'Ekout la anrejistre avèk siksè!',
            'data' => ['musicId' => $musicId],
            'errors' => []
        ]);
    }

    // 2. KREYE YON MIZIK
    if (empty($data['title']) || empty($data['artistId']) || empty($data['audioUrl'])) {
        jsonResponse([
            'success' => false,
            'message' => 'Tit, Atis, ak Fichye Odyo obligatwa pou anrejistre yon mizik.',
            'data' => null,
            'errors' => ['Missing required fields: title, artistId, audioUrl']
        ], 400);
    }

    $id = !empty($data['id']) ? $data['id'] : 'mus_' . time() . '_' . bin2hex(random_bytes(3));
    $title = trim($data['title']);
    $artistId = $data['artistId'];
    $artistName = $data['artistName'] ?? 'Atis UpMizik';
    $feat = $data['feat'] ?? null;
    $category = $data['category'] ?? 'Tout';
    $releaseFormat = $data['releaseFormat'] ?? 'single';
    $albumId = $data['album_id'] ?? $data['albumId'] ?? null;
    $albumName = $data['albumName'] ?? null;
    $trackNumber = isset($data['trackNumber']) ? (int)$data['trackNumber'] : 1;
    $coverUrl = $data['coverUrl'] ?? 'https://images.unsplash.com/photo-1514525253161-7a46d19cd819?w=500&auto=format&fit=crop&q=80';
    $audioUrl = $data['audioUrl'];
    $duration = isset($data['duration']) ? (int)$data['duration'] : 180;
    $status = $data['status'] ?? 'active';
    $youtubeUrl = $data['youtubeUrl'] ?? null;
    $tiktokUrl = $data['tiktokUrl'] ?? null;
    $instagramUrl = $data['instagramUrl'] ?? null;

    try {
        // Asire atis la egziste nan tablo artists dabò pou foreign key constraint (fk_music_artist) pa janm echwe
        $chkArt = $pdo->prepare("SELECT id FROM artists WHERE id = ?");
        $chkArt->execute([$artistId]);
        if (!$chkArt->fetchColumn()) {
            $insArt = $pdo->prepare("
                INSERT INTO artists (id, name, stageName, email, phone, status, registrationDate)
                VALUES (?, ?, ?, ?, '50900000000', 'active', NOW())
                ON DUPLICATE KEY UPDATE stageName = VALUES(stageName), status = 'active'
            ");
            $insArt->execute([
                $artistId,
                $artistName,
                $artistName,
                $data['artistEmail'] ?? ($artistId . '@upmizik.com')
            ]);
        }

        $stmt = $pdo->prepare("
            INSERT INTO musics (
                id, title, artistId, artistName, feat, category, releaseFormat, 
                album_id, albumName, trackNumber, coverUrl, audioUrl, duration, 
                status, youtubeUrl, tiktokUrl, instagramUrl, created_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, 
                ?, ?, ?, ?, ?, ?, 
                ?, ?, ?, ?, NOW()
            )
            ON DUPLICATE KEY UPDATE
                title = VALUES(title),
                artistName = VALUES(artistName),
                feat = VALUES(feat),
                category = VALUES(category),
                releaseFormat = VALUES(releaseFormat),
                album_id = VALUES(album_id),
                albumName = VALUES(albumName),
                trackNumber = VALUES(trackNumber),
                coverUrl = VALUES(coverUrl),
                audioUrl = VALUES(audioUrl),
                duration = VALUES(duration),
                status = VALUES(status),
                youtubeUrl = VALUES(youtubeUrl),
                tiktokUrl = VALUES(tiktokUrl),
                instagramUrl = VALUES(instagramUrl)
        ");

        $stmt->execute([
            $id, $title, $artistId, $artistName, $feat, $category, $releaseFormat,
            $albumId, $albumName, $trackNumber, $coverUrl, $audioUrl, $duration,
            $status, $youtubeUrl, $tiktokUrl, $instagramUrl
        ]);

        // Anrejistre Kredi (Credits / Split Sheet) si yo voye yo
        if (!empty($data['credits']) && is_array($data['credits'])) {
            $delCredits = $pdo->prepare("DELETE FROM music_credits WHERE musicId = ?");
            $delCredits->execute([$id]);

            $insCred = $pdo->prepare("
                INSERT INTO music_credits (id, musicId, name, artistId, role, percentage, phone, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            foreach ($data['credits'] as $cred) {
                $credId = !empty($cred['id']) ? $cred['id'] : 'cred_' . time() . '_' . bin2hex(random_bytes(2));
                $insCred->execute([
                    $credId,
                    $id,
                    $cred['name'] ?? '',
                    $cred['artistId'] ?? null,
                    $cred['role'] ?? 'Kreyatè',
                    $cred['percentage'] ?? 0,
                    $cred['phone'] ?? null,
                    $cred['notes'] ?? null
                ]);
            }
        }

        jsonResponse([
            'success' => true,
            'message' => 'Mizik la anrejistre avèk siksè nan baz done a!',
            'data' => [
                'musicId' => $id,
                'title' => $title
            ],
            'musicId' => $id,
            'errors' => []
        ], 201);
    } catch (PDOException $e) {
        jsonResponse([
            'success' => false,
            'message' => 'Erè SQL pandan anrejistreman mizik la: ' . $e->getMessage(),
            'data' => null,
            'errors' => [$e->getMessage()]
        ], 500);
    }
}

// ----------------------------------------------------------
// PUT / PATCH: Modifye yon mizik (oswa valide/rejte)
// ----------------------------------------------------------
if ($method === 'PUT' || $method === 'PATCH') {
    $data = getJsonInput();
    $id = $data['id'] ?? $_GET['id'] ?? null;

    if (!$id) {
        jsonResponse(['success' => false, 'message' => 'Id mizik la obligatwa.'], 400);
    }

    $fields = [];
    $params = [];

    $updatable = [
        'title', 'feat', 'category', 'releaseFormat', 'album_id', 'albumName',
        'trackNumber', 'coverUrl', 'audioUrl', 'duration', 'status',
        'rejectionReason', 'youtubeUrl', 'tiktokUrl', 'instagramUrl', 'position'
    ];

    foreach ($updatable as $f) {
        if (isset($data[$f])) {
            $fields[] = "`$f` = ?";
            $params[] = $data[$f];
        }
    }

    if (empty($fields)) {
        jsonResponse(['success' => false, 'message' => 'Pa gen done pou modifye.'], 400);
    }

    $params[] = $id;
    $sql = "UPDATE musics SET " . implode(', ', $fields) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    jsonResponse([
        'success' => true,
        'message' => 'Mizik la mete ajou avèk siksè!',
        'data' => ['musicId' => $id],
        'errors' => []
    ]);
}

// ----------------------------------------------------------
// DELETE: Efase yon mizik
// ----------------------------------------------------------
if ($method === 'DELETE') {
    $id = $_GET['id'] ?? getJsonInput()['id'] ?? null;

    if (!$id) {
        jsonResponse(['success' => false, 'message' => 'Id mizik la obligatwa.'], 400);
    }

    $stmt = $pdo->prepare("DELETE FROM musics WHERE id = ?");
    $stmt->execute([$id]);

    jsonResponse([
        'success' => true,
        'message' => 'Mizik la efase avèk siksè nan baz done a!',
        'data' => ['musicId' => $id],
        'errors' => []
    ]);
}

jsonResponse(['success' => false, 'message' => 'Metòd sa a pa sipòte.'], 405);
