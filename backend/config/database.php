<?php
/**
 * UpMizik - Database Connection Module (Hostinger / MySQL / PDO)
 */

require_once __DIR__ . '/env.php';

// Detekte ak sipòte tout fòm varyab anviwònman Coolify, Docker ak Hostinger
$rawHost = env('DB_HOST') ?: env('MYSQL_HOST') ?: env('MYSQL_URL_HOST');
$rawPort = env('DB_PORT') ?: env('MYSQL_PORT') ?: '3306';
$rawName = env('DB_NAME') ?: env('MYSQL_DATABASE') ?: env('DB_DATABASE') ?: 'upmiziknew';
$rawUser = env('DB_USER') ?: env('MYSQL_USER') ?: 'upmizik_user';
$rawPass = env('DB_PASS') ?: env('MYSQL_PASSWORD') ?: env('DB_PASSWORD') ?: 'upmizik_secure_pass_2026';

// Parse DATABASE_URL si Coolify bay yon URL konplè tankou: mysql://user:pass@host:port/dbname
$dbUrl = env('DATABASE_URL') ?: env('MYSQL_URL');
if ($dbUrl && ($parsed = parse_url($dbUrl))) {
    if (!empty($parsed['host'])) $rawHost = $parsed['host'];
    if (!empty($parsed['port'])) $rawPort = (string)$parsed['port'];
    if (!empty($parsed['user'])) $rawUser = $parsed['user'];
    if (!empty($parsed['pass'])) $rawPass = $parsed['pass'];
    if (!empty($parsed['path'])) $rawName = ltrim($parsed['path'], '/');
}

// 1. Nan nenpòt ka kote aplikasyon an anndan Docker (file_exists('/.dockerenv') se vre),
// fòse $rawHost la pou l toujou itilize 'upmizik-db'. Li dwe anile epi ranplase nenpòt IP ekstèn oswa host ki soti nan DATABASE_URL oswa lòt varyab anviwònman yo.
$isDocker = file_exists('/.dockerenv') || env('DOCKER_ENV') || getenv('COOLIFY_CONTAINER_NAME') || file_exists('/etc/docker');
if ($isDocker) {
    $rawHost = 'upmizik-db';
}

if (!$rawHost) {
    $rawHost = $isDocker ? 'upmizik-db' : 'localhost';
}

// 2. Asire w non baz de done a (fallback) mete sou 'upmiziknew'
if (empty($rawName) || $rawName === 'upmizik_db') {
    $rawName = 'upmiziknew';
}

define('DB_HOST', $rawHost);
define('DB_PORT', $rawPort);
define('DB_NAME', $rawName);
define('DB_USER', $rawUser);
define('DB_PASS', $rawPass);
define('SITE_URL', rtrim(env('SITE_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . ($_SERVER['HTTP_HOST'] ?? 'localhost')), '/'));

if (!function_exists('getDBConnection')) {
    function getDBConnection(): PDO {
        static $pdo = null;
        if ($pdo !== null) {
            return $pdo;
        }

        $hostsToTry = array_unique(array_filter([
            DB_HOST,
            'upmizik-db',
            'db',
            'mysql',
            '127.0.0.1',
            'localhost'
        ]));

        $lastException = null;
        $connectedHost = null;

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 4,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ];

        foreach ($hostsToTry as $candidateHost) {
            try {
                $dsn = "mysql:host=" . $candidateHost . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
                $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
                $connectedHost = $candidateHost;
                break;
            } catch (PDOException $e) {
                $lastException = $e;
            }
        }

        if (!$pdo && $lastException) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'message' => 'Erè koneksyon ak baz done MySQL: ' . $lastException->getMessage(),
                'data' => [
                    'tried_hosts' => $hostsToTry,
                    'port' => DB_PORT,
                    'database' => DB_NAME,
                    'user' => DB_USER
                ],
                'errors' => [$lastException->getMessage()]
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit();
        }

        // Silent schema resilience: Asire kolòn imaj ak prèv yo se LONGTEXT (pou evite erè 'Data too long' sou telefòn)
        try {
            $pdo->exec("
                ALTER TABLE `artistes` 
                MODIFY COLUMN `preuve_inscription_url` LONGTEXT NULL,
                MODIFY COLUMN `avatar_url` LONGTEXT NULL,
                MODIFY COLUMN `banniere_url` LONGTEXT NULL;
            ");
        } catch (Throwable $ignore) {}

        try {
            $pdo->exec("
                ALTER TABLE `dons` 
                MODIFY COLUMN `preuve_url` LONGTEXT NULL;
            ");
        } catch (Throwable $ignore) {}

        try {
            $pdo->exec("
                ALTER TABLE `musiques` 
                MODIFY COLUMN `cover_url` LONGTEXT NULL,
                MODIFY COLUMN `audio_url` LONGTEXT NULL;
            ");
        } catch (Throwable $ignore) {}

        return $pdo;
    }
}

if (!function_exists('jsonResponse')) {
    function jsonResponse($data, int $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');

        // Asire estrikti estanda: { success, message, data, errors }
        if (is_array($data)) {
            if (!isset($data['success'])) {
                $data['success'] = ($statusCode >= 200 && $statusCode < 300);
            }
            if (!isset($data['message'])) {
                $data['message'] = $data['success'] ? 'Siksè' : 'Gen yon erè ki pase';
            }
            if (!isset($data['data'])) {
                // Si gen lòt kle ki pa nan estanda a, mete yo nan data si data pa defini
                $keys = array_keys($data);
                $standardKeys = ['success', 'message', 'data', 'errors'];
                $extraKeys = array_diff($keys, $standardKeys);
                if (!empty($extraKeys) && !isset($data['data'])) {
                    $customData = [];
                    foreach ($extraKeys as $k) {
                        $customData[$k] = $data[$k];
                    }
                    $data['data'] = $customData;
                } else {
                    $data['data'] = $data['data'] ?? null;
                }
            }
            if (!isset($data['errors'])) {
                $data['errors'] = [];
            }
        }

        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }
}

if (!function_exists('getJsonInput')) {
    function getJsonInput(): array {
        $raw = file_get_contents('php://input');
        if (empty($raw)) {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}
