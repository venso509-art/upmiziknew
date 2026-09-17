<?php
/**
 * UpMizik - Database Connection Module (Coolify / Docker / Hostinger / MySQL / PDO)
 */

require_once __DIR__ . '/env.php';

// Si root config.php egziste (kote itilizatè a defini DB_USER, DB_PASS, elatriye), chaje li tou
$rootConfig1 = dirname(__DIR__, 2) . '/config.php';
$rootConfig2 = '/var/www/html/config.php';
if (file_exists($rootConfig1)) {
    @include_once $rootConfig1;
} elseif (file_exists($rootConfig2)) {
    @include_once $rootConfig2;
}

// Detekte ak sipòte tout fòm varyab anviwònman Coolify, Docker ak Hostinger
$rawHost = env('DB_HOST') ?: env('MYSQL_HOST') ?: env('MYSQL_URL_HOST') ?: (defined('DB_HOST') ? DB_HOST : null);
$rawPort = env('DB_PORT') ?: env('MYSQL_PORT') ?: '3306';
$rawName = env('DB_NAME') ?: env('MYSQL_DATABASE') ?: env('DB_DATABASE') ?: (defined('DB_NAME') ? DB_NAME : 'upmiziknew');
$rawUser = env('DB_USER') ?: env('MYSQL_USER') ?: (defined('DB_USER') ? DB_USER : 'upmizikuser');
$rawPass = env('DB_PASS') ?: env('MYSQL_PASSWORD') ?: env('DB_PASSWORD') ?: (defined('DB_PASS') ? DB_PASS : 'WLTsFLQlDffJzHxEIpr255SlXA9PS418uFYCBciPi6V8sj7358IjiQJ3XFInLUxs');

// Parse DATABASE_URL si Coolify bay yon URL konplè tankou: mysql://user:pass@host:port/dbname
$dbUrl = env('DATABASE_URL') ?: env('MYSQL_URL');
if ($dbUrl && ($parsed = parse_url($dbUrl))) {
    if (!empty($parsed['host'])) $rawHost = $parsed['host'];
    if (!empty($parsed['port'])) $rawPort = (string)$parsed['port'];
    if (!empty($parsed['user'])) $rawUser = $parsed['user'];
    if (!empty($parsed['pass'])) $rawPass = $parsed['pass'];
    if (!empty($parsed['path'])) $rawName = ltrim($parsed['path'], '/');
}

// 1. Nan nenpòt ka kote aplikasyon an anndan Docker (file_exists('/.dockerenv') se vre oswa Coolify),
// fòse $rawHost la pou l kòmanse ak 'upmizik-db' pou anpeche timeout sou IP ekstèn VPS la.
$isDocker = file_exists('/.dockerenv') || env('DOCKER_ENV') || getenv('COOLIFY_CONTAINER_NAME') || file_exists('/etc/docker');
if ($isDocker && (empty($rawHost) || $rawHost === '2.25.132.44' || filter_var($rawHost, FILTER_VALIDATE_IP))) {
    $rawHost = 'upmizik-db';
}

if (!$rawHost) {
    $rawHost = $isDocker ? 'upmizik-db' : 'localhost';
}

// 2. Asire w non baz de done a (fallback) mete sou 'upmiziknew'
if (empty($rawName) || $rawName === 'upmizik_db') {
    $rawName = 'upmiziknew';
}

if (!defined('DB_HOST')) define('DB_HOST', $rawHost);
if (!defined('DB_PORT')) define('DB_PORT', $rawPort);
if (!defined('DB_NAME')) define('DB_NAME', $rawName);
if (!defined('DB_USER')) define('DB_USER', $rawUser);
if (!defined('DB_PASS')) define('DB_PASS', $rawPass);
if (!defined('SITE_URL')) define('SITE_URL', rtrim(env('SITE_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . ($_SERVER['HTTP_HOST'] ?? 'localhost')), '/'));

if (!function_exists('getDBConnection')) {
    function getDBConnection(bool $throwOnError = false): PDO {
        static $pdo = null;
        if ($pdo !== null) {
            return $pdo;
        }

        // Fòse socket timeout a 2 segonn pou anpeche PHP bloke/jele 30 segonn si yon host pa reponn
        @ini_set('default_socket_timeout', 2);

        $primaryHost = defined('DB_HOST') ? DB_HOST : 'upmizik-db';
        $hostsToTry = array_unique(array_filter([
            $primaryHost,
            'upmizik-db',
            'db',
            'mysql',
            '172.17.0.1',
            'host.docker.internal',
            '127.0.0.1',
            'localhost'
        ]));

        $credentialsToTry = [
            [
                'user' => defined('DB_USER') ? DB_USER : 'upmizikuser',
                'pass' => defined('DB_PASS') ? DB_PASS : 'WLTsFLQlDffJzHxEIpr255SlXA9PS418uFYCBciPi6V8sj7358IjiQJ3XFInLUxs'
            ],
            [
                'user' => 'upmizikuser',
                'pass' => 'WLTsFLQlDffJzHxEIpr255SlXA9PS418uFYCBciPi6V8sj7358IjiQJ3XFInLUxs'
            ],
            [
                'user' => 'upmizik_user',
                'pass' => 'upmizik_secure_pass_2026'
            ],
            [
                'user' => 'root',
                'pass' => 'root_secure_pass_2026'
            ],
            [
                'user' => 'root',
                'pass' => 'WLTsFLQlDffJzHxEIpr255SlXA9PS418uFYCBciPi6V8sj7358IjiQJ3XFInLUxs'
            ]
        ];

        $databasesToTry = array_unique(array_filter([
            defined('DB_NAME') ? DB_NAME : 'upmiziknew',
            'upmiziknew',
            'upmizik_db'
        ]));

        $lastException = null;
        $connectedHost = null;
        $connectedDb = null;

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 2,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ];

        foreach ($hostsToTry as $candidateHost) {
            foreach ($credentialsToTry as $cred) {
                foreach ($databasesToTry as $candidateDb) {
                    try {
                        $dsn = "mysql:host={$candidateHost};port=" . DB_PORT . ";dbname={$candidateDb};charset=utf8mb4";
                        $testPdo = new PDO($dsn, $cred['user'], $cred['pass'], $options);
                        $pdo = $testPdo;
                        $connectedHost = $candidateHost;
                        $connectedDb = $candidateDb;
                        break 3;
                    } catch (PDOException $e) {
                        $lastException = $e;
                        // Si se "Unknown database", eseye kreye l si posib
                        if (str_contains($e->getMessage(), 'Unknown database') || $e->getCode() == 1049) {
                            try {
                                $rootDsn = "mysql:host={$candidateHost};port=" . DB_PORT . ";charset=utf8mb4";
                                $rootPdo = new PDO($rootDsn, $cred['user'], $cred['pass'], $options);
                                $rootPdo->exec("CREATE DATABASE IF NOT EXISTS `{$candidateDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
                                $pdo = new PDO("mysql:host={$candidateHost};port=" . DB_PORT . ";dbname={$candidateDb};charset=utf8mb4", $cred['user'], $cred['pass'], $options);
                                $connectedHost = $candidateHost;
                                $connectedDb = $candidateDb;
                                break 3;
                            } catch (Throwable $ignore) {}
                        }
                    }
                }
            }
        }

        if (!$pdo && $lastException) {
            if ($throwOnError) {
                throw $lastException;
            }
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'message' => 'Erè koneksyon ak baz done MySQL: ' . $lastException->getMessage(),
                'data' => [
                    'tried_hosts' => $hostsToTry,
                    'primary_host' => DB_HOST,
                    'port' => DB_PORT,
                    'database' => DB_NAME,
                    'user' => DB_USER
                ],
                'errors' => [$lastException->getMessage()]
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit();
        }

        // Silent schema initialization: Si tab 'artistes' la pa egziste, egzekite schema.sql otomatikman
        try {
            $checkTable = $pdo->query("SHOW TABLES LIKE 'artistes'")->fetch();
            if (!$checkTable) {
                $schemaFile = dirname(__DIR__) . '/database/schema.sql';
                if (file_exists($schemaFile)) {
                    $sqlContent = file_get_contents($schemaFile);
                    if (!empty($sqlContent)) {
                        $pdo->exec($sqlContent);
                    }
                }
            }
        } catch (Throwable $ignore) {}

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
