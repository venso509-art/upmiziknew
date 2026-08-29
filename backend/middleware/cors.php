<?php
/**
 * UpMizik - CORS Middleware (Cross-Origin Resource Sharing)
 */

require_once dirname(__DIR__) . '/config/env.php';

function handleCors() {
    $allowedOriginsEnv = env('ALLOWED_ORIGINS', '*');
    $appEnv = env('APP_ENV', 'production');
    $httpOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';

    if ($allowedOriginsEnv === '*' || $appEnv === 'development') {
        header("Access-Control-Allow-Origin: " . ($httpOrigin ?: '*'));
    } else {
        $allowedList = array_map('trim', explode(',', $allowedOriginsEnv));
        if (in_array($httpOrigin, $allowedList)) {
            header("Access-Control-Allow-Origin: {$httpOrigin}");
        } elseif (!empty($allowedList[0])) {
            header("Access-Control-Allow-Origin: {$allowedList[0]}");
        }
    }

    header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization, X-CSRF-Token");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Max-Age: 86400");

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit();
    }
}

handleCors();
