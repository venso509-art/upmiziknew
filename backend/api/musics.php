<?php
/**
 * UpMizik - Musics API Endpoint (Hostinger / MySQL / PDO)
 */

require_once dirname(__DIR__) . '/middleware/cors.php';
require_once dirname(__DIR__) . '/config/database.php';

$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

function mapStatusToDb(string $status): string {
    return match (strtolower($status)) {
        'active' => 'actif',
        'pending' => 'en_attente',
        'rejected' => 'rejete',
        'suspended' => 'suspendu',
        'validated' => 'valide',
        default => $status
    };
}

function mapStatusToFrontend(string $status): string {
    return match (strtolower($status)) {
        'actif' => 'active',
        'en_attente' => 'pending',
        'rejete' => 'rejected',
        'suspendu' => 'suspended',
        'valide' => 'validated',
        default => $status
    };
}

// ----------------------------------------------------------
// GET: Rekipere mizik (Endividyèl oswa Lis Filtre)
// ----------------------------------------------------------
if ($method === 'GET') {
    $action = $_GET['action'] ?? 'list';

    // 1. Enkremente kontè kout zòrèy (Stream / Listen)
    if ($action === 'listen' || $action === 'stream') {
        $musicId = $_GET['id'] ?? null;
        if (!$musicId) {
            jsonResponse(['success' => false, 'message' => 'Id mizik la obligatwa pou enkremente kout zòrèy.'], 400);
        }

        $stmt = $pdo->prepare("UPDATE musiques SET ecoutes = ecoutes + 1 WHERE id = ?");
        $stmt->execute([$musicId]);

        // Enkremente tou sou atis la si posib
        $artStmt = $pdo->prepare("SELECT artiste_id FROM musiques WHERE id = ?");
        $artStmt->execute([$musicId]);
        $artId = $artStmt->fetchColumn();
        if ($artId) {
            $pdo->prepare("UPDATE artistes SET total_ecoutes = total_ecoutes + 1 WHERE id = ?")->execute([$artId]);
        }

        jsonResponse([
            'success' => true,
            'message' => 'Kout zòrèy enkremente avèk siksè nan MySQL.',
            'musicId' => $musicId,
            'errors' => []
        ]);
    }

    // 2. Detay yon sèl mizik ak kredi li yo
    $id = $_GET['id'] ?? null;
    if ($id) {
        $stmt = $pdo->prepare("
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
                m.statut AS status,
                m.raison_rejet AS rejectionReason,
                m.date_creation AS created_at,
                a.avatar_url AS artistAvatar,
                a.nom_scene AS stageName,
                a.ville AS artistCity
            FROM musiques m
            LEFT JOIN artistes a ON m.artiste_id = a.id
            WHERE m.id = ?
        ");
        $stmt->execute([$id]);
        $music = $stmt->fetch();

        if ($music) {
            $music['status'] = mapStatusToFrontend($music['status'] ?? 'actif');

            // Rekipere kredi mizik la nan credits_musique
            $cStmt = $pdo->prepare("
                SELECT 
                    id,
                    musique_id AS musicId,
                    nom AS name,
                    artiste_id AS artistId,
                    role,
                    pourcentage AS percentage,
                    telephone AS phone,
                    notes
                FROM credits_musique 
                WHERE musique_id = ?
            ");
            $cStmt->execute([$id]);
            $music['credits'] = $cStmt->fetchAll();

            jsonResponse([
                'success' => true,
                'message' => 'Mizik la rekipere.',
                'data' => ['music' => $music],
                'music' => $music,
                'errors' => []
            ]);
        } else {
            jsonResponse([
                'success' => false,
                'message' => 'Mizik la pa jwenn nan baz done a.',
                'data' => null,
                'errors' => ['Music not found']
            ], 404);
        }
    }

    // 3. Lis tout mizik ak filtè
    $artistId = $_GET['artistId'] ?? null;
    $category = $_GET['category'] ?? null;
    $status = $_GET['status'] ?? 'active';
    $format = $_GET['releaseFormat'] ?? $_GET['format'] ?? null;
    $albumId = $_GET['album_id'] ?? $_GET['albumName'] ?? null;
    $sortBy = $_GET['sortBy'] ?? 'created_at';
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

    $query = "
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
            m.statut AS status,
            m.raison_rejet AS rejectionReason,
            m.date_creation AS created_at,
            a.avatar_url AS artistAvatar,
            a.nom_scene AS stageName
        FROM musiques m
        LEFT JOIN artistes a ON m.artiste_id = a.id
        WHERE 1=1
    ";
    $params = [];

    if ($artistId) {
        $query .= " AND m.artiste_id = ?";
        $params[] = $artistId;
    }

    if ($category && $category !== 'Tout') {
        $query .= " AND m.categorie = ?";
        $params[] = $category;
    }

    if ($format && $format !== 'all') {
        $query .= " AND m.format = ?";
        $params[] = $format;
    }

    if ($albumId) {
        $query .= " AND m.nom_album = ?";
        $params[] = $albumId;
    }

    if ($status && $status !== 'all') {
        $query .= " AND m.statut = ?";
        $params[] = mapStatusToDb($status);
    }

    if ($sortBy === 'position') {
        $query .= " ORDER BY m.position ASC, m.date_creation DESC";
    } elseif ($sortBy === 'listens') {
        $query .= " ORDER BY m.ecoutes DESC, m.date_creation DESC";
    } else {
        $query .= " ORDER BY m.date_creation DESC";
    }

    $query .= " LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $musics = $stmt->fetchAll();

    // Rekipere kredi pou tout mizik yo si genyen
    if (!empty($musics)) {
        $musicIds = array_column($musics, 'id');
        $placeholders = implode(',', array_fill(0, count($musicIds), '?'));
        $crStmt = $pdo->prepare("
            SELECT 
                id,
                musique_id AS musicId,
                nom AS name,
                artiste_id AS artistId,
                role,
                pourcentage AS percentage,
                telephone AS phone,
                notes
            FROM credits_musique 
            WHERE musique_id IN ($placeholders)
        ");
        $crStmt->execute($musicIds);
        $allCredits = $crStmt->fetchAll();

        $creditsByMusic = [];
        foreach ($allCredits as $cr) {
            $creditsByMusic[$cr['musicId']][] = $cr;
        }

        foreach ($musics as &$m) {
            $m['status'] = mapStatusToFrontend($m['status'] ?? 'actif');
            $m['credits'] = $creditsByMusic[$m['id']] ?? [];
        }
    }

    jsonResponse([
        'success' => true,
        'message' => 'Lis mizik rekipere.',
        'data' => [
            'musics' => $musics,
            'count' => count($musics)
        ],
        'musics' => $musics,
        'count' => count($musics),
        'errors' => []
    ]);
}

// ----------------------------------------------------------
// POST: Ajoute yon nouvo mizik
// ----------------------------------------------------------
if ($method === 'POST') {
    $data = getJsonInput();

    if (empty($data['title']) || empty($data['artistId']) || empty($data['audioUrl']) || empty($data['coverUrl'])) {
        jsonResponse([
            'success' => false,
            'message' => 'Tit, Atis, Lyen Odyo ak Lyen Kouvèti obligatwa.',
            'data' => null,
            'errors' => ['Missing required music fields']
        ], 400);
    }

    $id = !empty($data['id']) ? $data['id'] : 'mus_' . time() . '_' . bin2hex(random_bytes(3));
    $title = trim($data['title']);
    $artistId = $data['artistId'];
    $artistName = $data['artistName'] ?? 'Atis UpMizik';
    $feat = $data['feat'] ?? null;
    $category = $data['category'] ?? 'Tout';
    $rawFormat = strtolower($data['releaseFormat'] ?? $data['format'] ?? 'single');
    $validFormats = ['single', 'album', 'ep', 'mixtape', 'demo'];
    $releaseFormat = in_array($rawFormat, $validFormats) ? $rawFormat : 'single';
    $albumName = $data['albumName'] ?? $data['album_id'] ?? null;
    $trackNumber = (int)($data['trackNumber'] ?? 1);
    $coverUrl = $data['coverUrl'];
    $audioUrl = $data['audioUrl'];
    $duration = (int)($data['duration'] ?? 180);
    $status = mapStatusToDb($data['status'] ?? 'pending');
    $youtubeUrl = $data['youtubeUrl'] ?? null;
    $tiktokUrl = $data['tiktokUrl'] ?? null;
    $instagramUrl = $data['instagramUrl'] ?? null;

    try {
        // Asire atis la egziste nan artistes
        $chkArt = $pdo->prepare("SELECT id FROM artistes WHERE id = ?");
        $chkArt->execute([$artistId]);
        if (!$chkArt->fetch()) {
            $insArt = $pdo->prepare("
                INSERT INTO artistes (id, nom_complet, nom_scene, email, telephone, statut, date_inscription)
                VALUES (?, ?, ?, ?, '50900000000', 'actif', NOW())
                ON DUPLICATE KEY UPDATE nom_scene = VALUES(nom_scene), statut = 'actif'
            ");
            $insArt->execute([
                $artistId,
                $artistName,
                $artistName,
                'artist_' . $artistId . '@upmizik.com'
            ]);
        }

        $stmt = $pdo->prepare("
            INSERT INTO musiques (
                id, titre, artiste_id, nom_artiste, featuring, categorie, format,
                nom_album, numero_piste, cover_url, audio_url, duree,
                statut, youtube_url, tiktok_url, instagram_url, date_creation
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, NOW()
            )
            ON DUPLICATE KEY UPDATE
                titre = VALUES(titre),
                nom_artiste = VALUES(nom_artiste),
                featuring = VALUES(featuring),
                categorie = VALUES(categorie),
                format = VALUES(format),
                nom_album = VALUES(nom_album),
                numero_piste = VALUES(numero_piste),
                cover_url = VALUES(cover_url),
                audio_url = VALUES(audio_url),
                duree = VALUES(duree),
                statut = VALUES(statut),
                youtube_url = VALUES(youtube_url),
                tiktok_url = VALUES(tiktok_url),
                instagram_url = VALUES(instagram_url)
        ");

        $stmt->execute([
            $id, $title, $artistId, $artistName, $feat, $category, $releaseFormat,
            $albumName, $trackNumber, $coverUrl, $audioUrl, $duration,
            $status, $youtubeUrl, $tiktokUrl, $instagramUrl
        ]);

        // Ajoute Kredi yo nan credits_musique si yo voye yo
        if (!empty($data['credits']) && is_array($data['credits'])) {
            $pdo->prepare("DELETE FROM credits_musique WHERE musique_id = ?")->execute([$id]);

            $cIns = $pdo->prepare("
                INSERT INTO credits_musique (
                    id, musique_id, nom, artiste_id, role, pourcentage, telephone, notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            foreach ($data['credits'] as $cred) {
                $cid = $cred['id'] ?? ('cred_' . time() . '_' . bin2hex(random_bytes(2)));
                $cIns->execute([
                    $cid,
                    $id,
                    $cred['name'] ?? 'Kontribitè',
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

    $fieldMap = [
        'title' => 'titre',
        'feat' => 'featuring',
        'category' => 'categorie',
        'releaseFormat' => 'format',
        'format' => 'format',
        'album_id' => 'nom_album',
        'albumName' => 'nom_album',
        'trackNumber' => 'numero_piste',
        'coverUrl' => 'cover_url',
        'audioUrl' => 'audio_url',
        'duration' => 'duree',
        'status' => 'statut',
        'rejectionReason' => 'raison_rejet',
        'youtubeUrl' => 'youtube_url',
        'tiktokUrl' => 'tiktok_url',
        'instagramUrl' => 'instagram_url',
        'position' => 'position'
    ];

    $fields = [];
    $params = [];

    foreach ($fieldMap as $frontendKey => $dbCol) {
        if (isset($data[$frontendKey])) {
            $val = $data[$frontendKey];
            if ($frontendKey === 'status') {
                $val = mapStatusToDb($val);
            }
            $fields[] = "`$dbCol` = ?";
            $params[] = $val;
        }
    }

    if (empty($fields)) {
        jsonResponse(['success' => false, 'message' => 'Pa gen done pou modifye.'], 400);
    }

    $params[] = $id;
    $sql = "UPDATE musiques SET " . implode(', ', $fields) . " WHERE id = ?";
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

    $stmt = $pdo->prepare("DELETE FROM musiques WHERE id = ?");
    $stmt->execute([$id]);

    jsonResponse([
        'success' => true,
        'message' => 'Mizik la efase avèk siksè nan baz done a!',
        'data' => ['musicId' => $id],
        'errors' => []
    ]);
}

jsonResponse(['success' => false, 'message' => 'Metòd sa a pa sipòte.'], 405);
