<?php
/**
 * UpMizik - Secure File Upload API Endpoint (Hostinger Storage)
 */

require_once dirname(__DIR__) . '/middleware/cors.php';
require_once dirname(__DIR__) . '/config/database.php';

ini_set('upload_max_filesize', '128M');
ini_set('post_max_size', '128M');
ini_set('max_execution_time', '300');
ini_set('memory_limit', '256M');

$baseUploadDir = dirname(__DIR__) . '/uploads';

// Kreye tout sou-dosye yo
$folders = ['music', 'covers', 'proofs', 'avatars', 'banners', 'media', 'general'];
foreach ($folders as $folder) {
    $dirPath = $baseUploadDir . '/' . $folder;
    if (!file_exists($dirPath)) {
        @mkdir($dirPath, 0755, true);
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Sèlman metòd POST ki aksepte pou telechajman.', 'data' => null, 'errors' => ['Method not allowed']], 405);
}

$type = $_POST['type'] ?? $_GET['type'] ?? 'general';
$targetSubfolder = in_array($type, $folders) ? $type : 'general';
$targetDir = $baseUploadDir . '/' . $targetSubfolder;

// 1. UPLOAD MULTIPART FORMDATA ($_FILES)
if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['file'];
    $originalName = $file['name'];
    $tmpPath = $file['tmp_name'];
    $fileSize = $file['size'];

    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    // Bloke ekstansyon danjere
    $dangerousExtensions = ['php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'phps', 'cgi', 'pl', 'py', 'sh', 'exe', 'bat', 'cmd', 'js', 'html', 'htm', 'htaccess'];
    if (in_array($extension, $dangerousExtensions)) {
        jsonResponse([
            'success' => false,
            'message' => 'Ekstansyon fichye sa a entèdi pou rezon sekirite.',
            'data' => null,
            'errors' => ['Forbidden file extension']
        ], 403);
    }

    $allowedMusic = ['mp3', 'wav', 'aac', 'm4a', 'ogg', 'flac'];
    $allowedImages = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'];
    $allowedProofs = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

    $isAllowed = false;
    if ($targetSubfolder === 'music' && in_array($extension, $allowedMusic)) {
        $isAllowed = true;
    } elseif (in_array($targetSubfolder, ['covers', 'avatars', 'banners']) && in_array($extension, $allowedImages)) {
        $isAllowed = true;
    } elseif ($targetSubfolder === 'proofs' && in_array($extension, $allowedProofs)) {
        $isAllowed = true;
    } elseif (in_array($extension, array_merge($allowedMusic, $allowedImages, $allowedProofs))) {
        $isAllowed = true;
    }

    if (!$isAllowed) {
        jsonResponse([
            'success' => false,
            'message' => 'Fòma fichye a pa otorize (' . htmlspecialchars($extension) . ').',
            'data' => null,
            'errors' => ['Invalid file format']
        ], 422);
    }

    // Limit gwosè: Mizik = 128MB, Imaj = 10MB
    $maxLimit = ($targetSubfolder === 'music') ? 134217728 : 10485760;
    if ($fileSize > $maxLimit) {
        jsonResponse([
            'success' => false,
            'message' => 'Gwosè fichye a depase limit otorize a.',
            'data' => null,
            'errors' => ['File size limit exceeded']
        ], 422);
    }

    // Non inik oaza
    $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
    $safeName = substr($safeName, 0, 30);
    $uniqueFileName = $targetSubfolder . '_' . time() . '_' . bin2hex(random_bytes(4)) . '_' . $safeName . '.' . $extension;
    $destination = $targetDir . '/' . $uniqueFileName;

    if (move_uploaded_file($tmpPath, $destination)) {
        $relativePath = '/backend/uploads/' . $targetSubfolder . '/' . $uniqueFileName;
        $fullUrl = rtrim(SITE_URL, '/') . $relativePath;

        jsonResponse([
            'success' => true,
            'message' => 'Fichye a telechaje avèk siksè sou sèvè Hostinger a.',
            'data' => [
                'url' => $fullUrl,
                'relativePath' => $relativePath,
                'fileName' => $uniqueFileName,
                'originalName' => $originalName,
                'size' => $fileSize,
                'type' => $targetSubfolder
            ],
            'url' => $fullUrl,
            'relativePath' => $relativePath,
            'fileName' => $uniqueFileName,
            'errors' => []
        ], 201);
    } else {
        jsonResponse([
            'success' => false,
            'message' => 'Pa rive deplase fichye a nan dosye destinasyon an.',
            'data' => null,
            'errors' => ['Upload move failed']
        ], 500);
    }
}

// 2. UPLOAD BASE64 (DATA URI)
$json = getJsonInput();
if (!empty($json['base64Data'])) {
    $dataUri = $json['base64Data'];
    if (preg_match('/^data:(image\/(\w+)|audio\/(\w+)|application\/pdf);base64,/', $dataUri, $typeMatch)) {
        $dataWithoutHeader = substr($dataUri, strpos($dataUri, ',') + 1);
        $decoded = base64_decode($dataWithoutHeader);
        if ($decoded === false) {
            jsonResponse(['success' => false, 'message' => 'Dekodaj Base64 echwe.'], 400);
        }

        $ext = !empty($typeMatch[2]) ? $typeMatch[2] : (!empty($typeMatch[3]) ? $typeMatch[3] : 'png');
        if ($ext === 'jpeg') $ext = 'jpg';

        $uniqueFileName = $targetSubfolder . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $destination = $targetDir . '/' . $uniqueFileName;

        if (file_put_contents($destination, $decoded)) {
            $relativePath = '/backend/uploads/' . $targetSubfolder . '/' . $uniqueFileName;
            $fullUrl = rtrim(SITE_URL, '/') . $relativePath;

            jsonResponse([
                'success' => true,
                'message' => 'Fichye Base64 a anrejistre avèk siksè sou Hostinger.',
                'data' => [
                    'url' => $fullUrl,
                    'relativePath' => $relativePath,
                    'fileName' => $uniqueFileName
                ],
                'url' => $fullUrl,
                'relativePath' => $relativePath,
                'errors' => []
            ], 201);
        }
    }
}

jsonResponse([
    'success' => false,
    'message' => 'Pa gen okenn fichye valid ki voye.',
    'data' => null,
    'errors' => ['No file provided']
], 400);
