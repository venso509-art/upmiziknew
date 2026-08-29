<?php
/**
 * UpMizik - Artists API Endpoint (Hostinger / MySQL / PDO)
 */

require_once dirname(__DIR__) . '/middleware/cors.php';
require_once dirname(__DIR__) . '/config/database.php';

$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

// ----------------------------------------------------------
// GET: Lis Atis oswa Pwofil Atis
// ----------------------------------------------------------
if ($method === 'GET') {
    $id = $_GET['id'] ?? null;
    $status = $_GET['status'] ?? null;

    if ($id) {
        $stmt = $pdo->prepare("SELECT * FROM artists WHERE id = ?");
        $stmt->execute([$id]);
        $artist = $stmt->fetch();

        if ($artist) {
            unset($artist['pin']);
            $artist['isPaidThisMonth'] = (bool)($artist['isPaidThisMonth'] ?? false);

            // Rekipere tout mizik atis la
            $mStmt = $pdo->prepare("SELECT * FROM musics WHERE artistId = ? ORDER BY created_at DESC");
            $mStmt->execute([$id]);
            $artist['musics'] = $mStmt->fetchAll();

            jsonResponse([
                'success' => true,
                'message' => 'Pwofil atis la rekipere.',
                'data' => ['artist' => $artist],
                'artist' => $artist,
                'errors' => []
            ]);
        } else {
            jsonResponse([
                'success' => false,
                'message' => 'Atis la pa jwenn nan baz done a.',
                'data' => null,
                'errors' => ['Artist not found']
            ], 404);
        }
    } else {
        $query = "SELECT * FROM artists WHERE 1=1";
        $params = [];

        if ($status && $status !== 'all') {
            $query .= " AND status = ?";
            $params[] = $status;
        }

        $query .= " ORDER BY totalListens DESC, registrationDate DESC";
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $artists = $stmt->fetchAll();

        foreach ($artists as &$a) {
            unset($a['pin']);
            $a['isPaidThisMonth'] = (bool)($a['isPaidThisMonth'] ?? false);
        }

        jsonResponse([
            'success' => true,
            'message' => 'Lis atis rekipere.',
            'data' => ['artists' => $artists, 'count' => count($artists)],
            'artists' => $artists,
            'count' => count($artists),
            'errors' => []
        ]);
    }
}

// ----------------------------------------------------------
// POST: Enskri yon nouvo atis
// ----------------------------------------------------------
if ($method === 'POST') {
    $data = getJsonInput();

    if (empty($data['name']) || empty($data['stageName']) || empty($data['email']) || empty($data['phone'])) {
        jsonResponse([
            'success' => false,
            'message' => 'Non, Non Sèn, Imèl ak Nimewo Telefòn obligatwa.',
            'data' => null,
            'errors' => ['Missing required artist fields']
        ], 400);
    }

    $id = !empty($data['id']) ? $data['id'] : 'art_' . time() . '_' . bin2hex(random_bytes(3));
    $name = trim($data['name']);
    $stageName = trim($data['stageName']);
    $email = strtolower(trim($data['email']));
    $phone = trim($data['phone']);
    $city = $data['city'] ?? 'Pòtoprens';
    $pin = !empty($data['pin']) ? (strlen($data['pin']) === 60 ? $data['pin'] : password_hash($data['pin'], PASSWORD_BCRYPT, ['cost' => 10])) : password_hash('0000', PASSWORD_BCRYPT, ['cost' => 10]);
    $avatarUrl = $data['avatarUrl'] ?? 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=500&auto=format&fit=crop&q=80';
    $bio = $data['bio'] ?? null;
    $musicalRoots = $data['musicalRoots'] ?? null;
    $musicalInfluences = $data['musicalInfluences'] ?? null;
    $artisticVision = $data['artisticVision'] ?? null;
    $status = $data['status'] ?? 'pending';
    $registrationProofUrl = $data['registrationProofUrl'] ?? null;
    $youtubeUrl = $data['youtubeUrl'] ?? null;
    $instagramUrl = $data['instagramUrl'] ?? null;
    $instagramHandle = $data['instagramHandle'] ?? null;
    $tiktokUrl = $data['tiktokUrl'] ?? null;
    $tiktokHandle = $data['tiktokHandle'] ?? null;
    $twitterUrl = $data['twitterUrl'] ?? null;
    $twitterHandle = $data['twitterHandle'] ?? null;
    $headerBannerUrl = $data['headerBannerUrl'] ?? null;
    $bannerGenreTheme = $data['bannerGenreTheme'] ?? null;

    $stmt = $pdo->prepare("
        INSERT INTO artists (
            id, name, stageName, email, phone, city, pin, avatarUrl, bio,
            musicalRoots, musicalInfluences, artisticVision, status,
            registrationProofUrl, youtubeUrl, instagramUrl, instagramHandle,
            tiktokUrl, tiktokHandle, twitterUrl, twitterHandle, headerBannerUrl,
            bannerGenreTheme, registrationDate, created_at
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, NOW(), NOW()
        )
        ON DUPLICATE KEY UPDATE
            name = VALUES(name),
            stageName = VALUES(stageName),
            phone = VALUES(phone),
            city = VALUES(city),
            avatarUrl = VALUES(avatarUrl),
            bio = VALUES(bio),
            musicalRoots = VALUES(musicalRoots),
            musicalInfluences = VALUES(musicalInfluences),
            artisticVision = VALUES(artisticVision),
            status = VALUES(status),
            registrationProofUrl = VALUES(registrationProofUrl),
            youtubeUrl = VALUES(youtubeUrl),
            instagramUrl = VALUES(instagramUrl),
            instagramHandle = VALUES(instagramHandle),
            tiktokUrl = VALUES(tiktokUrl),
            tiktokHandle = VALUES(tiktokHandle),
            twitterUrl = VALUES(twitterUrl),
            twitterHandle = VALUES(twitterHandle),
            headerBannerUrl = VALUES(headerBannerUrl),
            bannerGenreTheme = VALUES(bannerGenreTheme)
    ");

    $stmt->execute([
        $id, $name, $stageName, $email, $phone, $city, $pin, $avatarUrl, $bio,
        $musicalRoots, $musicalInfluences, $artisticVision, $status,
        $registrationProofUrl, $youtubeUrl, $instagramUrl, $instagramHandle,
        $tiktokUrl, $tiktokHandle, $twitterUrl, $twitterHandle, $headerBannerUrl,
        $bannerGenreTheme
    ]);

    jsonResponse([
        'success' => true,
        'message' => 'Enskripsyon atis la fèt avèk siksè nan baz done a!',
        'data' => ['artistId' => $id],
        'artistId' => $id,
        'errors' => []
    ], 201);
}

// ----------------------------------------------------------
// PUT / PATCH: Modifye yon atis (oswa valide/rejte pa admin)
// ----------------------------------------------------------
if ($method === 'PUT' || $method === 'PATCH') {
    $data = getJsonInput();
    $id = $data['id'] ?? $_GET['id'] ?? null;

    if (!$id) {
        jsonResponse(['success' => false, 'message' => 'Id atis la obligatwa.'], 400);
    }

    $fields = [];
    $params = [];

    $updatable = [
        'name', 'stageName', 'phone', 'city', 'avatarUrl', 'bio',
        'musicalRoots', 'musicalInfluences', 'artisticVision', 'artistQuote',
        'status', 'registrationProofUrl', 'registrationRejectionReason',
        'youtubeUrl', 'instagramUrl', 'instagramHandle', 'tiktokUrl', 'tiktokHandle',
        'twitterUrl', 'twitterHandle', 'headerBannerUrl', 'bannerGenreTheme',
        'isPaidThisMonth', 'paidDateThisMonth', 'paidAmountThisMonth', 'paidReferenceThisMonth',
        'suspendedAt', 'suspendedUntil', 'suspensionDays', 'suspensionReason'
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
    $sql = "UPDATE artists SET " . implode(', ', $fields) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    jsonResponse([
        'success' => true,
        'message' => 'Pwofil atis la mete ajou avèk siksè!',
        'data' => ['artistId' => $id],
        'errors' => []
    ]);
}

// ----------------------------------------------------------
// DELETE: Efase yon atis
// ----------------------------------------------------------
if ($method === 'DELETE') {
    $id = $_GET['id'] ?? getJsonInput()['id'] ?? null;

    if (!$id) {
        jsonResponse(['success' => false, 'message' => 'Id atis la obligatwa.'], 400);
    }

    $stmt = $pdo->prepare("DELETE FROM artists WHERE id = ?");
    $stmt->execute([$id]);

    jsonResponse([
        'success' => true,
        'message' => 'Atis la efase avèk siksè nan baz done a!',
        'data' => ['artistId' => $id],
        'errors' => []
    ]);
}

jsonResponse(['success' => false, 'message' => 'Metòd sa a pa sipòte.'], 405);
