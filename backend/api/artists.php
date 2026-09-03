<?php
/**
 * UpMizik - Artists API Endpoint (Hostinger / MySQL / PDO)
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
// GET: Lis Atis oswa Pwofil Atis
// ----------------------------------------------------------
if ($method === 'GET') {
    $id = $_GET['id'] ?? null;
    $status = $_GET['status'] ?? null;

    if ($id) {
        $stmt = $pdo->prepare("
            SELECT 
                id,
                nom_scene AS stageName,
                nom_complet AS name,
                email,
                telephone AS phone,
                ville AS city,
                pin,
                avatar_url AS avatarUrl,
                bio,
                racines_musicales AS musicalRoots,
                influences AS musicalInfluences,
                vision_artistique AS artisticVision,
                citation AS artistQuote,
                statut AS status,
                preuve_inscription_url AS registrationProofUrl,
                raison_rejet AS registrationRejectionReason,
                total_ecoutes AS totalListens,
                total_dons_recus AS totalDonationsReceived,
                youtube_url AS youtubeUrl,
                instagram_url AS instagramUrl,
                tiktok_url AS tiktokUrl,
                banniere_url AS headerBannerUrl,
                theme_banniere AS bannerGenreTheme,
                paye_ce_mois AS isPaidThisMonth,
                date_paiement AS paidDateThisMonth,
                montant_paye AS paidAmountThisMonth,
                reference_paiement AS paidReferenceThisMonth,
                date_inscription AS registrationDate,
                date_inscription AS created_at
            FROM artistes 
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        $artist = $stmt->fetch();

        if ($artist) {
            unset($artist['pin']);
            $artist['status'] = mapStatusToFrontend($artist['status'] ?? 'actif');
            $artist['isPaidThisMonth'] = (bool)($artist['isPaidThisMonth'] ?? false);

            // Rekipere tout mizik atis la
            $mStmt = $pdo->prepare("
                SELECT 
                    id,
                    titre AS title,
                    artiste_id AS artistId,
                    nom_artiste AS artistName,
                    featuring AS feat,
                    categorie AS category,
                    format AS releaseFormat,
                    nom_album AS albumName,
                    numero_piste AS trackNumber,
                    cover_url AS coverUrl,
                    audio_url AS audioUrl,
                    duree AS duration,
                    ecoutes AS listens,
                    total_dons AS totalDonations,
                    statut AS status,
                    date_creation AS created_at
                FROM musiques 
                WHERE artiste_id = ? 
                ORDER BY date_creation DESC
            ");
            $mStmt->execute([$id]);
            $musics = $mStmt->fetchAll();
            foreach ($musics as &$m) {
                $m['status'] = mapStatusToFrontend($m['status'] ?? 'actif');
            }
            $artist['musics'] = $musics;

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
        $query = "
            SELECT 
                id,
                nom_scene AS stageName,
                nom_complet AS name,
                email,
                telephone AS phone,
                ville AS city,
                pin,
                avatar_url AS avatarUrl,
                bio,
                racines_musicales AS musicalRoots,
                influences AS musicalInfluences,
                vision_artistique AS artisticVision,
                citation AS artistQuote,
                statut AS status,
                preuve_inscription_url AS registrationProofUrl,
                raison_rejet AS registrationRejectionReason,
                total_ecoutes AS totalListens,
                total_dons_recus AS totalDonationsReceived,
                youtube_url AS youtubeUrl,
                instagram_url AS instagramUrl,
                tiktok_url AS tiktokUrl,
                banniere_url AS headerBannerUrl,
                theme_banniere AS bannerGenreTheme,
                paye_ce_mois AS isPaidThisMonth,
                date_paiement AS paidDateThisMonth,
                montant_paye AS paidAmountThisMonth,
                reference_paiement AS paidReferenceThisMonth,
                date_inscription AS registrationDate,
                date_inscription AS created_at
            FROM artistes 
            WHERE 1=1
        ";
        $params = [];

        if ($status && $status !== 'all') {
            $query .= " AND statut = ?";
            $params[] = mapStatusToDb($status);
        }

        $query .= " ORDER BY total_ecoutes DESC, date_inscription DESC";
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $artists = $stmt->fetchAll();

        foreach ($artists as &$a) {
            unset($a['pin']);
            $a['status'] = mapStatusToFrontend($a['status'] ?? 'actif');
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
    $status = mapStatusToDb($data['status'] ?? 'pending');
    $registrationProofUrl = $data['registrationProofUrl'] ?? null;
    $youtubeUrl = $data['youtubeUrl'] ?? null;
    $instagramUrl = $data['instagramUrl'] ?? null;
    $tiktokUrl = $data['tiktokUrl'] ?? null;
    $headerBannerUrl = $data['headerBannerUrl'] ?? null;
    $bannerGenreTheme = $data['bannerGenreTheme'] ?? null;

    $stmt = $pdo->prepare("
        INSERT INTO artistes (
            id, nom_complet, nom_scene, email, telephone, ville, pin, avatar_url, bio,
            racines_musicales, influences, vision_artistique, statut,
            preuve_inscription_url, youtube_url, instagram_url,
            tiktok_url, banniere_url,
            theme_banniere, date_inscription
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?,
            ?, ?,
            ?, NOW()
        )
        ON DUPLICATE KEY UPDATE
            nom_complet = VALUES(nom_complet),
            nom_scene = VALUES(nom_scene),
            telephone = VALUES(telephone),
            ville = VALUES(ville),
            avatar_url = VALUES(avatar_url),
            bio = VALUES(bio),
            racines_musicales = VALUES(racines_musicales),
            influences = VALUES(influences),
            vision_artistique = VALUES(vision_artistique),
            statut = VALUES(statut),
            preuve_inscription_url = VALUES(preuve_inscription_url),
            youtube_url = VALUES(youtube_url),
            instagram_url = VALUES(instagram_url),
            tiktok_url = VALUES(tiktok_url),
            banniere_url = VALUES(banniere_url),
            theme_banniere = VALUES(theme_banniere)
    ");

    $stmt->execute([
        $id, $name, $stageName, $email, $phone, $city, $pin, $avatarUrl, $bio,
        $musicalRoots, $musicalInfluences, $artisticVision, $status,
        $registrationProofUrl, $youtubeUrl, $instagramUrl,
        $tiktokUrl, $headerBannerUrl,
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

    $fieldMap = [
        'name' => 'nom_complet',
        'stageName' => 'nom_scene',
        'phone' => 'telephone',
        'city' => 'ville',
        'avatarUrl' => 'avatar_url',
        'bio' => 'bio',
        'musicalRoots' => 'racines_musicales',
        'musicalInfluences' => 'influences',
        'artisticVision' => 'vision_artistique',
        'artistQuote' => 'citation',
        'status' => 'statut',
        'registrationProofUrl' => 'preuve_inscription_url',
        'registrationRejectionReason' => 'raison_rejet',
        'youtubeUrl' => 'youtube_url',
        'instagramUrl' => 'instagram_url',
        'tiktokUrl' => 'tiktok_url',
        'headerBannerUrl' => 'banniere_url',
        'bannerGenreTheme' => 'theme_banniere',
        'isPaidThisMonth' => 'paye_ce_mois',
        'paidDateThisMonth' => 'date_paiement',
        'paidAmountThisMonth' => 'montant_paye',
        'paidReferenceThisMonth' => 'reference_paiement'
    ];

    $fields = [];
    $params = [];

    foreach ($fieldMap as $frontendKey => $dbCol) {
        if (isset($data[$frontendKey])) {
            $val = $data[$frontendKey];
            if ($frontendKey === 'status') {
                $val = mapStatusToDb($val);
            } elseif ($frontendKey === 'isPaidThisMonth') {
                $val = !empty($val) ? 1 : 0;
            }
            $fields[] = "`$dbCol` = ?";
            $params[] = $val;
        }
    }

    if (empty($fields)) {
        jsonResponse(['success' => false, 'message' => 'Pa gen done pou modifye.'], 400);
    }

    $params[] = $id;
    $sql = "UPDATE artistes SET " . implode(', ', $fields) . " WHERE id = ?";
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

    $stmt = $pdo->prepare("DELETE FROM artistes WHERE id = ?");
    $stmt->execute([$id]);

    jsonResponse([
        'success' => true,
        'message' => 'Atis la efase avèk siksè nan baz done a!',
        'data' => ['artistId' => $id],
        'errors' => []
    ]);
}

jsonResponse(['success' => false, 'message' => 'Metòd sa a pa sipòte.'], 405);
