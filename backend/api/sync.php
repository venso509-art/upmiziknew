<?php
/**
 * UpMizik - Bulk Sync & Seed Data API Endpoint (Hostinger / MySQL)
 * 
 * Pèmèt migrasyon ak senkronizasyon tout done inisyal yo dirèkteman nan MySQL
 */

require_once dirname(__DIR__) . '/middleware/cors.php';
require_once dirname(__DIR__) . '/config/database.php';

$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Sèlman POST aksepte pou senkronizasyon.'], 405);
}

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

$data = getJsonInput();

// 1. Senkronize Atis yo nan `artistes`
if (!empty($data['artists']) && is_array($data['artists'])) {
    $artStmt = $pdo->prepare("
        INSERT INTO artistes (
            id, nom_complet, nom_scene, email, telephone, ville, pin, avatar_url, bio,
            racines_musicales, influences, vision_artistique, citation,
            statut, preuve_inscription_url, date_inscription, total_ecoutes, total_dons_recus,
            banniere_url, theme_banniere, paye_ce_mois
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            nom_complet = VALUES(nom_complet),
            nom_scene = VALUES(nom_scene),
            telephone = VALUES(telephone),
            ville = VALUES(ville),
            pin = VALUES(pin),
            avatar_url = VALUES(avatar_url),
            bio = VALUES(bio),
            statut = VALUES(statut),
            total_ecoutes = VALUES(total_ecoutes),
            total_dons_recus = VALUES(total_dons_recus),
            banniere_url = VALUES(banniere_url),
            paye_ce_mois = VALUES(paye_ce_mois)
    ");

    foreach ($data['artists'] as $a) {
        $artStmt->execute([
            $a['id'],
            $a['name'] ?? $a['stageName'],
            $a['stageName'],
            $a['email'],
            $a['phone'] ?? '',
            $a['city'] ?? 'Pòtoprens',
            $a['pin'] ?? '0000',
            $a['avatarUrl'] ?? '',
            $a['bio'] ?? '',
            $a['musicalRoots'] ?? null,
            $a['musicalInfluences'] ?? null,
            $a['artisticVision'] ?? null,
            $a['artistQuote'] ?? null,
            mapStatusToDb($a['status'] ?? 'active'),
            $a['registrationProofUrl'] ?? null,
            $a['registrationDate'] ?? date('Y-m-d H:i:s'),
            $a['totalListens'] ?? 0,
            $a['totalDonationsReceived'] ?? 0,
            $a['headerBannerUrl'] ?? null,
            $a['bannerGenreTheme'] ?? null,
            !empty($a['isPaidThisMonth']) ? 1 : 0
        ]);
    }
}

// 2. Senkronize Mizik yo nan `musiques`
if (!empty($data['musics']) && is_array($data['musics'])) {
    $musStmt = $pdo->prepare("
        INSERT INTO musiques (
            id, titre, artiste_id, nom_artiste, featuring, categorie, format,
            nom_album, numero_piste, cover_url, audio_url, duree,
            ecoutes, total_dons, statut, date_creation
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
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
            ecoutes = VALUES(ecoutes),
            total_dons = VALUES(total_dons),
            statut = VALUES(statut)
    ");

    foreach ($data['musics'] as $m) {
        // Asire atis la egziste dabò pou evite foreign key constraint error
        $chk = $pdo->prepare("SELECT id FROM artistes WHERE id = ?");
        $chk->execute([$m['artistId']]);
        if (!$chk->fetch()) {
            $createArt = $pdo->prepare("
                INSERT INTO artistes (id, nom_complet, nom_scene, email, telephone, statut, date_inscription)
                VALUES (?, ?, ?, ?, '50900000000', 'actif', NOW())
                ON DUPLICATE KEY UPDATE nom_scene = VALUES(nom_scene), statut = 'actif'
            ");
            $createArt->execute([
                $m['artistId'],
                $m['artistName'] ?? 'Atis UpMizik',
                $m['artistName'] ?? 'Atis UpMizik',
                'artist_' . $m['artistId'] . '@upmizik.com'
            ]);
        }

        $rawFormat = strtolower($m['releaseFormat'] ?? $m['format'] ?? 'single');
        $validFormats = ['single', 'album', 'ep', 'mixtape', 'demo'];
        $format = in_array($rawFormat, $validFormats) ? $rawFormat : 'single';

        $musStmt->execute([
            $m['id'],
            $m['title'],
            $m['artistId'],
            $m['artistName'] ?? 'Atis',
            $m['feat'] ?? null,
            $m['category'] ?? 'Tout',
            $format,
            $m['albumName'] ?? $m['album_id'] ?? null,
            $m['trackNumber'] ?? 1,
            $m['coverUrl'],
            $m['audioUrl'],
            $m['duration'] ?? 180,
            $m['listens'] ?? 0,
            $m['totalDonations'] ?? 0,
            mapStatusToDb($m['status'] ?? 'active')
        ]);
    }
}

// 3. Senkronize Piblisite nan `publicites`
if (!empty($data['pubs']) && is_array($data['pubs'])) {
    $pubStmt = $pdo->prepare("
        INSERT INTO publicites (id, titre, description, image_url, lien_url, actif, nom_sponsor)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            titre = VALUES(titre),
            description = VALUES(description),
            image_url = VALUES(image_url),
            lien_url = VALUES(lien_url),
            actif = VALUES(actif),
            nom_sponsor = VALUES(nom_sponsor)
    ");
    foreach ($data['pubs'] as $p) {
        $pubStmt->execute([
            $p['id'],
            $p['title'],
            $p['description'] ?? '',
            $p['imageUrl'],
            $p['linkUrl'] ?? '#',
            !empty($p['active']) ? 1 : 0,
            $p['sponsorName'] ?? 'Sponsor'
        ]);
    }
}

// 4. Senkronize RPA nan `configurations`
if (!empty($data['rpa']) && is_array($data['rpa'])) {
    $rpaJson = json_encode($data['rpa'], JSON_UNESCAPED_UNICODE);
    $rpaStmt = $pdo->prepare("
        INSERT INTO configurations (cle, valeur, description)
        VALUES ('rpa_items', ?, 'Lis pwojè ak revelasyon atis (RPA)')
        ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)
    ");
    $rpaStmt->execute([$rpaJson]);
}

jsonResponse(['success' => true, 'message' => 'Tout done yo senkronize avèk siksè nan MySQL!']);
