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
    require_once dirname(__DIR__) . '/config/database.php';

    $response['db_debug'] = [
        'target_host' => DB_HOST,
        'target_port' => DB_PORT,
        'target_user' => DB_USER,
        'target_db' => DB_NAME
    ];
    $pdo = getDBConnection();
    $response['database'] = 'connected';
} catch (Throwable $e) {
    $response['database'] = 'disconnected (' . $e->getMessage() . ')';
}

http_response_code(200);
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
exit();
