<?php
/**
 * UpMizik - Social Posts & Feed API Endpoint (Hostinger / MySQL / publications_sociales)
 */

require_once dirname(__DIR__) . '/middleware/cors.php';
require_once dirname(__DIR__) . '/config/database.php';

$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

// ----------------------------------------------------------
// GET: Rekipere tout piblikasyon sosyal yo ak kòmantè yo
// ----------------------------------------------------------
if ($method === 'GET') {
    $postId = $_GET['id'] ?? null;
    $artistId = $_GET['artistId'] ?? null;

    if ($postId) {
        $stmt = $pdo->prepare("
            SELECT 
                p.id,
                p.artiste_id AS artistId,
                p.nom_artiste AS artistName,
                p.nom_artiste AS stageName,
                p.avatar_artiste AS artistAvatar,
                p.contenu AS content,
                p.image_url AS imageUrl,
                p.musique_attachee_id AS associatedSongId,
                p.likes,
                p.nombre_commentaires AS commentsCount,
                p.date_creation AS created_at,
                'twitter' AS platform,
                CONCAT('@', LOWER(REPLACE(p.nom_artiste, ' ', ''))) AS handle,
                p.date_creation AS timestamp
            FROM publications_sociales p 
            WHERE p.id = ?
        ");
        $stmt->execute([$postId]);
        $post = $stmt->fetch();

        if ($post) {
            $post['tags'] = [];
            $post['isPinned'] = false;
            
            $comStmt = $pdo->prepare("
                SELECT 
                    id,
                    musique_id AS postId,
                    nom_auteur AS authorName,
                    avatar_auteur AS authorAvatar,
                    contenu AS content,
                    likes,
                    date_creation AS created_at
                FROM commentaires_musique 
                WHERE musique_id = ? 
                ORDER BY date_creation ASC
            ");
            $comStmt->execute([$postId]);
            $post['comments'] = $comStmt->fetchAll();

            jsonResponse(['success' => true, 'post' => $post]);
        } else {
            jsonResponse(['success' => false, 'message' => 'Piblikasyon an pa jwenn.'], 404);
        }
    } else {
        $query = "
            SELECT 
                p.id,
                p.artiste_id AS artistId,
                p.nom_artiste AS artistName,
                p.nom_artiste AS stageName,
                p.avatar_artiste AS artistAvatar,
                p.contenu AS content,
                p.image_url AS imageUrl,
                p.musique_attachee_id AS associatedSongId,
                p.likes,
                p.nombre_commentaires AS commentsCount,
                p.date_creation AS created_at,
                'twitter' AS platform,
                CONCAT('@', LOWER(REPLACE(p.nom_artiste, ' ', ''))) AS handle,
                p.date_creation AS timestamp
            FROM publications_sociales p 
            WHERE 1=1
        ";
        $params = [];
        if ($artistId) {
            $query .= " AND p.artiste_id = ?";
            $params[] = $artistId;
        }
        $query .= " ORDER BY p.date_creation DESC";
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $posts = $stmt->fetchAll();

        foreach ($posts as &$p) {
            $p['tags'] = [];
            $p['isPinned'] = false;
        }

        jsonResponse(['success' => true, 'posts' => $posts, 'count' => count($posts)]);
    }
}

// ----------------------------------------------------------
// POST: Ajoute yon nouvo piblikasyon sosyal oswa kòmantè
// ----------------------------------------------------------
if ($method === 'POST') {
    $data = getJsonInput();
    $action = $data['action'] ?? 'post';

    // A. Kòmantè sou yon post / mizik
    if ($action === 'comment') {
        $postId = $data['postId'] ?? null;
        $content = trim($data['content'] ?? '');
        $authorName = trim($data['authorName'] ?? 'Fanatik UpMizik');
        $authorAvatar = $data['authorAvatar'] ?? null;

        if (!$postId || empty($content)) {
            jsonResponse(['success' => false, 'message' => 'postId ak content obligatwa.'], 400);
        }

        $comId = 'com_' . time() . '_' . bin2hex(random_bytes(2));
        $stmt = $pdo->prepare("
            INSERT INTO commentaires_musique (id, musique_id, nom_auteur, avatar_auteur, contenu, likes, date_creation)
            VALUES (?, ?, ?, ?, ?, 0, NOW())
        ");
        $stmt->execute([$comId, $postId, $authorName, $authorAvatar, $content]);

        // Ogmante kontè kòmantè nan publications_sociales si sa aplikab
        $pUp = $pdo->prepare("UPDATE publications_sociales SET nombre_commentaires = nombre_commentaires + 1 WHERE id = ?");
        $pUp->execute([$postId]);

        jsonResponse(['success' => true, 'message' => 'Kòmantè pibliye.', 'commentId' => $comId]);
    }

    // B. Like yon post
    if ($action === 'like') {
        $postId = $data['postId'] ?? null;
        if (!$postId) {
            jsonResponse(['success' => false, 'message' => 'postId obligatwa.'], 400);
        }
        $stmt = $pdo->prepare("UPDATE publications_sociales SET likes = likes + 1 WHERE id = ?");
        $stmt->execute([$postId]);
        jsonResponse(['success' => true, 'message' => 'Like anrejistre.']);
    }

    // C. Kreye yon nouvo post
    if (empty($data['artistId']) || empty($data['content'])) {
        jsonResponse(['success' => false, 'message' => 'artistId ak kontni obligatwa.'], 400);
    }

    $id = !empty($data['id']) ? $data['id'] : ('post_' . time() . '_' . bin2hex(random_bytes(3)));
    $artistId = $data['artistId'];
    $artistName = $data['artistName'] ?? $data['stageName'] ?? 'Atis';
    $artistAvatar = $data['artistAvatar'] ?? null;
    $content = trim($data['content']);
    $imageUrl = $data['imageUrl'] ?? null;
    $associatedSongId = $data['associatedSongId'] ?? null;

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
        INSERT INTO publications_sociales (
            id, artiste_id, nom_artiste, avatar_artiste, contenu, image_url,
            musique_attachee_id, likes, nombre_commentaires, date_creation
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 0, 0, NOW())
    ");

    $stmt->execute([
        $id, $artistId, $artistName, $artistAvatar, $content, $imageUrl,
        $associatedSongId
    ]);

    jsonResponse(['success' => true, 'message' => 'Piblikasyon kreye avèk siksè.', 'postId' => $id], 201);
}

// ----------------------------------------------------------
// DELETE: Efase yon post
// ----------------------------------------------------------
if ($method === 'DELETE') {
    $id = $_GET['id'] ?? null;
    if (!$id) {
        jsonResponse(['success' => false, 'message' => 'ID post obligatwa.'], 400);
    }
    $stmt = $pdo->prepare("DELETE FROM publications_sociales WHERE id = ?");
    $stmt->execute([$id]);
    jsonResponse(['success' => true, 'message' => 'Post efase avèk siksè.']);
}

jsonResponse(['success' => false, 'message' => 'Metòd sa a pa sipòte.'], 405);
