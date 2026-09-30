<?php
/**
 * UpMizik - CORS Middleware (Cross-Origin Resource Sharing)
 */

require_once dirname(__DIR__) . '/config/env.php';

function handleCors() {
    $httpOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
    
    // Always permit origin dynamically so mobile browsers, webviews, and preview never get blocked
    if (!empty($httpOrigin)) {
        header("Access-Control-Allow-Origin: {$httpOrigin}");
    } else {
        header("Access-Control-Allow-Origin: *");
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
