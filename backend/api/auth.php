<?php
/**
 * UpMizik - Authentication API Endpoint (Hostinger / MySQL / Session / Rate Limit)
 */

require_once dirname(__DIR__) . '/middleware/cors.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/middleware/auth.php';

$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

// ----------------------------------------------------------
// GET: Tcheke Sesyon aktif oswa mande CSRF Token
// ----------------------------------------------------------
if ($method === 'GET') {
    $action = $_GET['action'] ?? 'check_session';

    if ($action === 'csrf') {
        jsonResponse([
            'success' => true,
            'message' => 'CSRF token pwodui avèk siksè.',
            'data' => [
                'csrf_token' => generateCsrfToken()
            ],
            'errors' => []
        ]);
    }

    if ($action === 'check_session') {
        if (isset($_SESSION['admin_user'])) {
            jsonResponse([
                'success' => true,
                'message' => 'Sesyon admin aktif.',
                'data' => [
                    'authenticated' => true,
                    'user' => $_SESSION['admin_user'],
                    'role' => 'admin',
                    'csrf_token' => generateCsrfToken()
                ],
                'errors' => []
            ]);
        } elseif (isset($_SESSION['artist_user'])) {
            jsonResponse([
                'success' => true,
                'message' => 'Sesyon atis aktif.',
                'data' => [
                    'authenticated' => true,
                    'user' => $_SESSION['artist_user'],
                    'role' => 'artist',
                    'csrf_token' => generateCsrfToken()
                ],
                'errors' => []
            ]);
        } else {
            jsonResponse([
                'success' => false,
                'message' => 'Pa gen okenn sesyon aktif.',
                'data' => [
                    'authenticated' => false
                ],
                'errors' => []
            ], 200);
        }
    }
}

// ----------------------------------------------------------
// POST: Login Admin, Login Atis, Logout, Chanje PIN
// ----------------------------------------------------------
if ($method === 'POST') {
    $data = getJsonInput();
    $action = $data['action'] ?? $_GET['action'] ?? 'admin_login';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    // 1. LOGOUT
    if ($action === 'logout') {
        session_unset();
        session_destroy();
        jsonResponse([
            'success' => true,
            'message' => 'Ou dekonekte avèk siksè.',
            'data' => null,
            'errors' => []
        ]);
    }

    // 2. ADMIN LOGIN (Super Admin / Admin Panel)
    if ($action === 'admin_login' || $action === 'admin') {
        $username = trim($data['username'] ?? $data['email'] ?? '');
        $password = trim($data['password'] ?? $data['pin'] ?? '');

        if (empty($username) || empty($password)) {
            jsonResponse([
                'success' => false,
                'message' => 'Non itilizatè/imèl ak modpas obligatwa.',
                'data' => null,
                'errors' => ['Missing username or password']
            ], 400);
        }

        // Tcheke Rate Limiting
        $rateLimit = checkRateLimit($pdo, $username, $ip, 5, 15);
        if (!$rateLimit['allowed']) {
            recordLoginAttempt($pdo, $username, $username, $ip, false);
            jsonResponse([
                'success' => false,
                'message' => $rateLimit['message'],
                'data' => null,
                'errors' => ['Rate limited']
            ], 429);
        }

        // Tcheke nan tab `admins`
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE (LOWER(username) = LOWER(?) OR LOWER(email) = LOWER(?)) AND status = 'active' LIMIT 1");
        $stmt->execute([$username, $username]);
        $admin = $stmt->fetch();

        $adminAuthenticated = false;
        $adminData = null;

        if ($admin && password_verify($password, $admin['password_hash'])) {
            $adminAuthenticated = true;
            $adminData = [
                'id' => $admin['id'],
                'username' => $admin['username'],
                'email' => $admin['email'],
                'name' => $admin['name'] ?? 'Administratè UpMizik',
                'role' => $admin['role'] ?? 'super_admin'
            ];
        } else {
            // Tcheke Master Admin Secret ki nan .env kòm fallback sekirize
            $masterEmail = env('ADMIN_EMAIL', 'admin@upmizik.com');
            $masterSecret = env('ADMIN_SECRET', 'AdminUpMizik2026Secure!');

            if ((strtolower($username) === strtolower($masterEmail) || $username === 'admin') && $password === $masterSecret) {
                $adminAuthenticated = true;
                $adminData = [
                    'id' => 'admin_super_master',
                    'username' => 'admin',
                    'email' => $masterEmail,
                    'name' => 'Super Admin UpMizik',
                    'role' => 'super_admin'
                ];
            }
        }

        if ($adminAuthenticated && $adminData) {
            session_regenerate_id(true);
            $_SESSION['admin_user'] = $adminData;
            $_SESSION['LAST_ACTIVITY'] = time();
            recordLoginAttempt($pdo, $username, $adminData['email'], $ip, true);

            // Mete ajou last_login si se nan tab admins
            if ($admin && isset($admin['id'])) {
                $updateLogin = $pdo->prepare("UPDATE admins SET last_login = NOW() WHERE id = ?");
                $updateLogin->execute([$admin['id']]);
            }

            jsonResponse([
                'success' => true,
                'message' => 'Koneksyon admin reyisi!',
                'data' => [
                    'user' => $adminData,
                    'role' => 'admin',
                    'csrf_token' => generateCsrfToken()
                ],
                'errors' => []
            ]);
        } else {
            recordLoginAttempt($pdo, $username, $username, $ip, false);
            jsonResponse([
                'success' => false,
                'message' => 'Non itilizatè oswa modpas admin pa kòrèk.',
                'data' => null,
                'errors' => ['Invalid credentials']
            ], 401);
        }
    }

    // 3. ATIS LOGIN (Artist Portal)
    if ($action === 'artist_login' || $action === 'login') {
        $identifier = trim($data['identifier'] ?? $data['email'] ?? $data['phone'] ?? '');
        $pin = trim($data['pin'] ?? '');

        if (empty($identifier) || empty($pin)) {
            jsonResponse([
                'success' => false,
                'message' => 'Imèl/Telefòn ak Kòd PIN obligatwa.',
                'data' => null,
                'errors' => ['Missing identifier or pin']
            ], 400);
        }

        $rateLimit = checkRateLimit($pdo, $identifier, $ip, 6, 15);
        if (!$rateLimit['allowed']) {
            recordLoginAttempt($pdo, $identifier, $identifier, $ip, false);
            jsonResponse([
                'success' => false,
                'message' => $rateLimit['message'],
                'data' => null,
                'errors' => ['Rate limited']
            ], 429);
        }

        $stmt = $pdo->prepare("
            SELECT * FROM artists 
            WHERE (LOWER(email) = LOWER(?) OR phone = ?)
            LIMIT 1
        ");
        $stmt->execute([$identifier, $identifier]);
        $artist = $stmt->fetch();

        if ($artist && ($artist['pin'] === $pin || password_verify($pin, $artist['pin']))) {
            session_regenerate_id(true);
            $artist['isPaidThisMonth'] = (bool)($artist['isPaidThisMonth'] ?? false);
            unset($artist['pin']);

            $_SESSION['artist_user'] = $artist;
            $_SESSION['LAST_ACTIVITY'] = time();
            recordLoginAttempt($pdo, $identifier, $artist['email'] ?? $identifier, $ip, true);

            jsonResponse([
                'success' => true,
                'message' => 'Koneksyon atis reyisi!',
                'data' => [
                    'artist' => $artist,
                    'user' => $artist,
                    'role' => 'artist',
                    'csrf_token' => generateCsrfToken()
                ],
                'artist' => $artist,
                'errors' => []
            ]);
        } else {
            recordLoginAttempt($pdo, $identifier, $identifier, $ip, false);
            jsonResponse([
                'success' => false,
                'message' => 'Imèl oswa Kòd PIN pa kòrèk.',
                'data' => null,
                'errors' => ['Invalid credentials']
            ], 401);
        }
    }

    // 4. CHANJE KÒD PIN ATIS
    if ($action === 'change_pin') {
        $artistId = $data['artistId'] ?? null;
        $oldPin = $data['oldPin'] ?? '';
        $newPin = $data['newPin'] ?? '';

        if (!$artistId || strlen($newPin) < 4) {
            jsonResponse([
                'success' => false,
                'message' => 'Nouvo PIN lan dwe gen omwen 4 chif.',
                'data' => null,
                'errors' => ['Invalid pin format']
            ], 400);
        }

        $stmt = $pdo->prepare("SELECT id, pin FROM artists WHERE id = ?");
        $stmt->execute([$artistId]);
        $artist = $stmt->fetch();

        if (!$artist || !($artist['pin'] === $oldPin || password_verify($oldPin, $artist['pin']))) {
            jsonResponse([
                'success' => false,
                'message' => 'Ansyen PIN lan pa kòrèk.',
                'data' => null,
                'errors' => ['Invalid old pin']
            ], 401);
        }

        $hashedNewPin = password_hash($newPin, PASSWORD_BCRYPT, ['cost' => 10]);
        $update = $pdo->prepare("UPDATE artists SET pin = ? WHERE id = ?");
        $update->execute([$hashedNewPin, $artistId]);

        jsonResponse([
            'success' => true,
            'message' => 'Kòd PIN ou a chanje avèk siksè.',
            'data' => null,
            'errors' => []
        ]);
    }
}

jsonResponse(['success' => false, 'message' => 'Metòd sa a pa sipòte.', 'data' => null, 'errors' => ['Method not allowed']], 405);
