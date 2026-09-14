<?php
/**
 * UpMizik - Database Diagnostic Endpoint
 * Egzamine an detay ki valè varyab anviwònman PHP resevwa
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once dirname(__DIR__) . '/config/env.php';

$diag = [
    'timestamp' => date('Y-m-d H:i:s'),
    'env_vars_detected' => [
        'DB_HOST' => env('DB_HOST'),
        'DB_PORT' => env('DB_PORT'),
        'DB_NAME' => env('DB_NAME'),
        'DB_USER' => env('DB_USER'),
        'HAS_DB_PASS' => !empty(env('DB_PASS')),
        'HAS_DATABASE_URL' => !empty(env('DATABASE_URL')),
        'HAS_MYSQL_URL' => !empty(env('MYSQL_URL'))
    ],
    'server_remote_addr' => $_SERVER['REMOTE_ADDR'] ?? null,
    'php_os' => PHP_OS,
    'dns_check' => []
];

// Eseye rezoud kèk non host
$hostsToTest = ['db', 'upmizik-db', 'host.docker.internal', 'localhost', '172.17.0.1'];
foreach ($hostsToTest as $h) {
    $ip = gethostbyname($h);
    $diag['dns_check'][$h] = ($ip !== $h) ? $ip : 'cannot resolve';
}

echo json_encode($diag, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
exit();
