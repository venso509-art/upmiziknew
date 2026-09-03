<?php
/**
 * UpMizik - Authentication & Security Middleware (Sessions, Admin RBAC, CSRF & Rate Limit)
 */

require_once dirname(__DIR__) . '/config/database.php';

// Konfigire bon paramèt sesyon PHP anvan session_start()
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Lax');
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
    }
    session_start();
}

/**
 * Verifikasyon sesyon ak timeout (30 minit)
 */
function checkSessionTimeout(int $timeoutSeconds = 1800): void {
    if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > $timeoutSeconds)) {
        session_unset();
        session_destroy();
        jsonResponse([
            'success' => false,
            'message' => 'Sesyon ou a ekspire. Tanpri rekonekte.',
            'data' => null,
            'errors' => ['Session expired']
        ], 401);
    }
    $_SESSION['LAST_ACTIVITY'] = time();
}

/**
 * Verifye si se yon Admin ki otantifye
 */
function requireAdminAuth(): array {
    checkSessionTimeout();

    // 1. Tcheke si gen sesyon admin aktif
    if (isset($_SESSION['admin_user']) && !empty($_SESSION['admin_user']['id'])) {
        return $_SESSION['admin_user'];
    }

    // 2. Tcheke si gen Authorization header Bearer token / Admin secret
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        $token = trim($matches[1]);
        $masterAdminSecret = env('ADMIN_SECRET', 'AdminUpMizik2026Secure!');
        if ($token === $masterAdminSecret && !empty($masterAdminSecret)) {
            return [
                'id' => 'admin_super',
                'name' => 'Super Admin UpMizik',
                'email' => env('ADMIN_EMAIL', 'admin@upmizik.com'),
                'role' => 'super_admin'
            ];
        }
    }

    jsonResponse([
        'success' => false,
        'message' => 'Ou pa otorize pou w fè aksyon sa a. Koneksyon admin obligatwa.',
        'data' => null,
        'errors' => ['Unauthorized']
    ], 401);
}

/**
 * Jenere ak verifye CSRF Tokens
 */
function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(?string $token): bool {
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Rate Limiting senp sou tantativ login (IP + Identifiant)
 */
function checkRateLimit(PDO $pdo, string $identifier, string $ip, int $maxAttempts = 5, int $blockMinutes = 15): array {
    $now = date('Y-m-d H:i:s');
    
    // Netwaye ansyen blokaj ki ekspire
    $cleanup = $pdo->prepare("DELETE FROM blocages_securite WHERE expire_a < ?");
    $cleanup->execute([$now]);

    // Tcheke si adrès IP an bloke
    $stmt = $pdo->prepare("SELECT * FROM blocages_securite WHERE ip = ?");
    $stmt->execute([$ip]);
    $block = $stmt->fetch();

    if ($block && strtotime($block['expire_a']) > time()) {
        $remaining = ceil((strtotime($block['expire_a']) - time()) / 60);
        return [
            'allowed' => false,
            'message' => "Twòp tantativ echwe. Aksè bloke pou {$remaining} minit.",
            'blocked_until' => $block['expire_a']
        ];
    }

    // Konte tantativ ki echwe nan 15 dènye minit yo
    $timeWindow = date('Y-m-d H:i:s', time() - ($blockMinutes * 60));
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) FROM tentatives_connexion 
        WHERE (identifiant = ? OR ip = ?) AND reussi = 0 AND date_tentative >= ?
    ");
    $countStmt->execute([$identifier, $ip, $timeWindow]);
    $attempts = (int)$countStmt->fetchColumn();

    if ($attempts >= $maxAttempts) {
        $blockUntil = date('Y-m-d H:i:s', time() + ($blockMinutes * 60));
        $insBlock = $pdo->prepare("
            INSERT INTO blocages_securite (ip, motif, date_blocage, expire_a)
            VALUES (?, 'Twòp tantativ koneksyon ki echwe', NOW(), ?)
            ON DUPLICATE KEY UPDATE expire_a = VALUES(expire_a)
        ");
        $insBlock->execute([$ip, $blockUntil]);

        return [
            'allowed' => false,
            'message' => "Kont ou bloke pou sekirite pandan {$blockMinutes} minit akoz plizyè tantativ ki echwe.",
            'blocked_until' => $blockUntil
        ];
    }

    return ['allowed' => true, 'remaining_attempts' => max(0, $maxAttempts - $attempts)];
}

/**
 * Anrejistre tantativ login
 */
function recordLoginAttempt(PDO $pdo, string $identifier, string $email, string $ip, bool $success, ?string $userAgent = null): void {
    $stmt = $pdo->prepare("
        INSERT INTO tentatives_connexion (identifiant, ip, reussi, date_tentative)
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->execute([$identifier, $ip, $success ? 1 : 0]);
}
