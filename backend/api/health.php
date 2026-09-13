<?php
/**
 * UpMizik - Healthcheck Endpoint pou Coolify & Docker
 * Tcheke si PHP, dosye uploads, ak baz done ap mache byen
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$response = [
    'status' => 'ok',
    'service' => 'UpMizik Backend',
    'timestamp' => date('Y-m-d H:i:s'),
    'php_version' => PHP_VERSION,
    'uploads_writable' => is_writable(__DIR__ . '/../uploads'),
    'database' => 'untested'
];

// Eseye verifye koneksyon baz done a san li pa fè crash oswa exit(500)
try {
    require_once dirname(__DIR__) . '/config/env.php';
    $rawHost = env('DB_HOST') ?: env('MYSQL_HOST') ?: env('MYSQL_URL_HOST') ?: 'db';
    $rawPort = env('DB_PORT') ?: env('MYSQL_PORT') ?: '3306';
    $rawName = env('DB_NAME') ?: env('MYSQL_DATABASE') ?: env('DB_DATABASE') ?: 'upmizik_db';
    $rawUser = env('DB_USER') ?: env('MYSQL_USER') ?: 'upmizik_user';
    $rawPass = env('DB_PASS') ?: env('MYSQL_PASSWORD') ?: env('DB_PASSWORD') ?: '';

    $dsn = "mysql:host={$rawHost};port={$rawPort};dbname={$rawName};charset=utf8mb4";
    $testPdo = new PDO($dsn, $rawUser, $rawPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 2
    ]);
    $response['database'] = 'connected';
} catch (Throwable $e) {
    $response['database'] = 'disconnected (' . $e->getMessage() . ')';
}

http_response_code(200);
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
exit();
