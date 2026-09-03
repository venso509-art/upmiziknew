<?php
/**
 * UpMizik - Real-Time Server-Sent Events (SSE) Stream Endpoint
 * 
 * Pèmèt kliyan konekte yo resevwa nouvo mizik, pòs sosyal, ak mizajou an tan reyèl.
 * Konpatib 100% ak Nginx sou Ubuntu 22.04 / aaPanel (X-Accel-Buffering: no).
 */

require_once dirname(__DIR__) . '/middleware/cors.php';
require_once dirname(__DIR__) . '/config/database.php';

// Fèmen buffering pou evènman yo soti imedyatman
if (function_exists('apache_setenv')) {
    @apache_setenv('no-gzip', '1');
}
@ini_set('zlib.output_compression', '0');
@ini_set('implicit_flush', '1');
while (ob_get_level() > 0) {
    ob_end_flush();
}
ob_implicit_flush(1);

header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache, no-transform');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Nginx buffering bypass sou aaPanel / Ubuntu

// Koneksyon MySQL
$pdo = getDBConnection();

// Rekipere dènye eta mizik aktyèl la nan musiques
$lastMusicCount = 0;
$lastMaxMusicId = '';

try {
    $stmt = $pdo->query("SELECT COUNT(*) as cnt, MAX(id) as max_id FROM musiques WHERE statut = 'actif'");
    $row = $stmt->fetch();
    $lastMusicCount = (int)($row['cnt'] ?? 0);
    $lastMaxMusicId = (string)($row['max_id'] ?? '');
} catch (Exception $e) {
    // Si tab la vid oswa erè
}

echo "event: connected\n";
echo "data: " . json_encode([
    'type' => 'connected',
    'musicCount' => $lastMusicCount,
    'serverTime' => time()
]) . "\n\n";
@flush();

$startTime = time();
$maxDuration = 25; // 25 segonn maksimòm pa rekèt pou libere worker PHP-FPM, kliyan ap re-konekte otomatikman

while ((time() - $startTime) < $maxDuration) {
    if (connection_aborted()) {
        break;
    }

    try {
        // Tcheke si gen nouvo mizik ki ajoute oswa modifye
        $stmt = $pdo->query("SELECT COUNT(*) as cnt, MAX(id) as max_id FROM musiques WHERE statut = 'actif'");
        $row = $stmt->fetch();
        $currCount = (int)($row['cnt'] ?? 0);
        $currMaxId = (string)($row['max_id'] ?? '');

        if ($currCount !== $lastMusicCount || $currMaxId !== $lastMaxMusicId) {
            $lastMusicCount = $currCount;
            $lastMaxMusicId = $currMaxId;

            // Chache dènye mizik yo
            $q = $pdo->query("
                SELECT 
                    m.id,
                    m.titre AS title,
                    m.artiste_id AS artistId,
                    m.nom_artiste AS artistName,
                    m.featuring AS feat,
                    m.categorie AS category,
                    m.format AS releaseFormat,
                    m.nom_album AS albumName,
                    m.numero_piste AS trackNumber,
                    m.cover_url AS coverUrl,
                    m.audio_url AS audioUrl,
                    m.duree AS duration,
                    m.ecoutes AS listens,
                    m.total_dons AS totalDonations,
                    'active' AS status,
                    m.date_creation AS created_at,
                    a.avatar_url AS artistAvatar,
                    a.nom_scene AS stageName 
                FROM musiques m 
                LEFT JOIN artistes a ON m.artiste_id = a.id 
                WHERE m.statut = 'actif' 
                ORDER BY m.date_creation DESC 
                LIMIT 10
            ");
            $latest = $q->fetchAll();

            echo "event: music_update\n";
            echo "data: " . json_encode([
                'type' => 'music_update',
                'count' => $currCount,
                'latest' => $latest,
                'timestamp' => time()
            ]) . "\n\n";
            @flush();
        }
    } catch (Exception $e) {
        // Kontinye silansye
    }

    // Ping / Heartbeat pou kenbe koneksyon an vivan
    echo ": ping\n\n";
    @flush();

    sleep(3);
}
