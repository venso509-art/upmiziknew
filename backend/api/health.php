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

// Eseye verifye koneksyon baz done a si varyab yo prezan
try {
    require_once dirname(__DIR__) . '/config/database.php';
    $pdo = getDBConnection();
    if ($pdo) {
        $response['database'] = 'connected';
    }
} catch (Throwable $e) {
    $response['database'] = 'error: ' . $e->getMessage();
}

http_response_code(200);
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
exit();
