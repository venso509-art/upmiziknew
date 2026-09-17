<?php
/**
 * UpMizik - Database Diagnostic Endpoint
 * Egzamine an detay ki valè varyab anviwònman PHP resevwa ak tès koneksyon an tan reyèl
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once dirname(__DIR__) . '/config/database.php';

$diag = [
    'timestamp' => date('Y-m-d H:i:s'),
    'db_constants' => [
        'DB_HOST' => defined('DB_HOST') ? DB_HOST : null,
        'DB_PORT' => defined('DB_PORT') ? DB_PORT : null,
        'DB_NAME' => defined('DB_NAME') ? DB_NAME : null,
        'DB_USER' => defined('DB_USER') ? DB_USER : null,
        'HAS_DB_PASS' => defined('DB_PASS') && !empty(DB_PASS)
    ],
    'env_vars_detected' => [
        'DB_HOST' => env('DB_HOST'),
        'DB_PORT' => env('DB_PORT'),
        'DB_NAME' => env('DB_NAME'),
        'DB_USER' => env('DB_USER'),
        'HAS_DB_PASS' => !empty(env('DB_PASS')),
        'HAS_DB_PASSWORD' => !empty(env('DB_PASSWORD')),
        'HAS_MYSQL_PASSWORD' => !empty(env('MYSQL_PASSWORD')),
        'HAS_DATABASE_URL' => !empty(env('DATABASE_URL')),
        'HAS_MYSQL_URL' => !empty(env('MYSQL_URL'))
    ],
    'server_remote_addr' => $_SERVER['REMOTE_ADDR'] ?? null,
    'php_os' => PHP_OS,
    'dns_check' => []
];

// Eseye rezoud kèk non host
$hostsToTest = ['upmizik-db', 'db', 'mysql', '10.0.1.1', '172.17.0.1', 'localhost'];
foreach ($hostsToTest as $h) {
    if (filter_var($h, FILTER_VALIDATE_IP)) {
        $diag['dns_check'][$h] = 'IP Address';
    } else {
        $ip = @gethostbyname($h);
        $diag['dns_check'][$h] = ($ip !== $h) ? $ip : 'cannot resolve';
    }
}

// Tès koneksyon an
try {
    $pdo = getDBConnection(true);
    $diag['connection_status'] = 'SUCCESS';
    
    // Konte done ki nan tablo yo
    $counts = [];
    foreach (['artistes', 'musiques', 'dons', 'utilisateurs', 'pubs', 'rpa', 'social_posts'] as $tab) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM `{$tab}`");
            $counts[$tab] = (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
            $counts[$tab] = 'Tab pa egziste: ' . $e->getMessage();
        }
    }
    $diag['table_counts'] = $counts;
} catch (Throwable $e) {
    $diag['connection_status'] = 'FAILED: ' . $e->getMessage();
}

echo json_encode($diag, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
exit();
