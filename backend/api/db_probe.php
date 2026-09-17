<?php
/**
 * UpMizik - DB Probe & Network Inspector for Coolify / Docker
 * Tès koneksyon TCP rapid (1 segonn) san bloke PHP
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// Jwenn tout varyab anviwònman ki gen rapò ak DB, san ekspoze modpas konplè
$envVars = [];
$allEnv = array_merge($_ENV, getenv(), $_SERVER);
foreach ($allEnv as $key => $val) {
    if (!is_string($val)) continue;
    $upper = strtoupper($key);
    if (str_contains($upper, 'DB') || str_contains($upper, 'MYSQL') || str_contains($upper, 'COOLIFY') || str_contains($upper, 'HOST') || str_contains($upper, 'PORT')) {
        if (str_contains($upper, 'PASS') || str_contains($upper, 'SECRET') || str_contains($upper, 'KEY')) {
            $envVars[$key] = empty($val) ? '(empty)' : (substr($val, 0, 3) . '***' . substr($val, -3) . ' (length ' . strlen($val) . ')');
        } else {
            $envVars[$key] = $val;
        }
    }
}

// Jwenn default gateway IP nan /proc/net/route si l egziste
$gatewayIp = null;
if (file_exists('/proc/net/route') && is_readable('/proc/net/route')) {
    $routes = file('/proc/net/route');
    foreach ($routes as $line) {
        $parts = preg_split('/\s+/', trim($line));
        if (isset($parts[1]) && $parts[1] === '00000000' && isset($parts[2])) {
            $gatewayIp = long2ip(hexdec(implode('', array_reverse(str_split($parts[2], 2)))));
            break;
        }
    }
}

// Lis kandida pou tcheke pò TCP 3306 (avèk 1 segonn timeout sèlman)
$candidates = array_unique(array_filter([
    $envVars['DB_HOST'] ?? null,
    $gatewayIp,
    '10.0.1.1',
    '172.17.0.1',
    'upmizik-db',
    'db',
    'mysql',
    'host.docker.internal',
    '127.0.0.1',
    'localhost'
]));

$tcpResults = [];
foreach ($candidates as $host) {
    if (empty($host)) continue;
    $t0 = microtime(true);
    $fp = @stream_socket_client("tcp://{$host}:3306", $errno, $errstr, 1, STREAM_CLIENT_CONNECT);
    $dt = round((microtime(true) - $t0) * 1000, 1);
    if ($fp) {
        fclose($fp);
        $tcpResults[$host] = "OPEN (responded in {$dt}ms)";
    } else {
        $tcpResults[$host] = "CLOSED/TIMEOUT ({$errstr}, code {$errno} in {$dt}ms)";
    }
}

echo json_encode([
    'timestamp' => date('Y-m-d H:i:s'),
    'detected_gateway' => $gatewayIp,
    'server_addr' => $_SERVER['SERVER_ADDR'] ?? null,
    'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? null,
    'db_env_vars' => $envVars,
    'tcp_port_3306_tests' => $tcpResults
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
exit();
