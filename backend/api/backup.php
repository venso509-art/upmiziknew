<?php
/**
 * UpMizik - Database Backup & Export API (Hostinger VPS / Admin Security)
 */

require_once dirname(__DIR__) . '/middleware/cors.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/middleware/auth.php';

$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

// Tcheke Otantifikasyon Admin
$admin = requireAdminAuth();

// ----------------------------------------------------------
// GET / POST: Jenere yon Backup SQL / GZ oswa Lis Backup ki Fèt
// ----------------------------------------------------------
if ($method === 'GET' || $method === 'POST') {
    $action = $_GET['action'] ?? (getJsonInput()['action'] ?? 'list');

    // 1. LIS TOUT BACKUP KI NAN DOSYE /backups/ AK BAZ DONE
    if ($action === 'list') {
        $backupDir = dirname(__DIR__) . '/backups';
        $files = [];

        if (file_exists($backupDir)) {
            $scan = scandir($backupDir);
            foreach ($scan as $f) {
                if (str_ends_with($f, '.sql') || str_ends_with($f, '.tar.gz') || str_ends_with($f, '.gz')) {
                    $filePath = $backupDir . '/' . $f;
                    $files[] = [
                        'filename' => $f,
                        'size' => filesize($filePath),
                        'created_at' => date('Y-m-d H:i:s', filemtime($filePath)),
                        'download_url' => SITE_URL . '/backend/api/backup.php?action=download&file=' . urlencode($f)
                    ];
                }
            }
        }

        // Tcheke tou nan tab backup_logs
        $logs = [];
        try {
            $stmt = $pdo->query("SELECT * FROM backup_logs ORDER BY created_at DESC LIMIT 50");
            $logs = $stmt->fetchAll();
        } catch (Exception $e) {
            // Tab la ka poko egziste
        }

        jsonResponse([
            'success' => true,
            'message' => 'Lis backup rekipere.',
            'data' => [
                'files' => $files,
                'logs' => $logs
            ],
            'errors' => []
        ]);
    }

    // 2. DOWNLOAD YON FICHE BACKUP
    if ($action === 'download') {
        $file = basename($_GET['file'] ?? '');
        $filePath = dirname(__DIR__) . '/backups/' . $file;

        if (empty($file) || !file_exists($filePath)) {
            jsonResponse(['success' => false, 'message' => 'Fichye backup la pa jwenn.'], 404);
        }

        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit();
    }

    // 3. KREYE YON NOUVO BACKUP SQL SOU SÈVÈ A
    if ($action === 'create' || $action === 'export') {
        $backupDir = dirname(__DIR__) . '/backups';
        if (!file_exists($backupDir)) {
            @mkdir($backupDir, 0750, true);
        }

        $filename = 'upmizik_db_backup_' . date('Y-m-d_His') . '.sql';
        $filepath = $backupDir . '/' . $filename;

        // Jenere SQL Dump via PDO
        $tables = [];
        $stmt = $pdo->query("SHOW TABLES");
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }

        $sqlContent = "-- UpMizik Hostinger Database Backup\n";
        $sqlContent .= "-- Generated on: " . date('Y-m-d H:i:s') . "\n";
        $sqlContent .= "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n";

        foreach ($tables as $table) {
            $createStmt = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_NUM);
            $sqlContent .= "DROP TABLE IF EXISTS `{$table}`;\n";
            $sqlContent .= $createStmt[1] . ";\n\n";

            $rowsStmt = $pdo->query("SELECT * FROM `{$table}`");
            $rows = $rowsStmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($rows)) {
                foreach ($rows as $r) {
                    $keys = array_map(fn($k) => "`{$k}`", array_keys($r));
                    $values = array_map(function($v) use ($pdo) {
                        return $v === null ? "NULL" : $pdo->quote($v);
                    }, array_values($r));

                    $sqlContent .= "INSERT INTO `{$table}` (" . implode(', ', $keys) . ") VALUES (" . implode(', ', $values) . ");\n";
                }
                $sqlContent .= "\n";
            }
        }

        $sqlContent .= "SET FOREIGN_KEY_CHECKS = 1;\n";

        file_put_contents($filepath, $sqlContent);

        // Anrejistre nan backup_logs si tab la egziste
        try {
            $logId = 'bck_' . time() . '_' . bin2hex(random_bytes(2));
            $pdo->prepare("
                INSERT INTO backup_logs (id, filename, file_size, status, created_by, created_at)
                VALUES (?, ?, ?, 'success', ?, NOW())
            ")->execute([$logId, $filename, filesize($filepath), $admin['username'] ?? 'admin']);
        } catch (Exception $e) {
            // Ignorer si tab logs la poko migre
        }

        jsonResponse([
            'success' => true,
            'message' => 'Backup baz done a kreye avèk siksè!',
            'data' => [
                'filename' => $filename,
                'size' => filesize($filepath),
                'download_url' => SITE_URL . '/backend/api/backup.php?action=download&file=' . urlencode($filename)
            ],
            'errors' => []
        ]);
    }
}

jsonResponse(['success' => false, 'message' => 'Aksyon pa rekonèt.'], 400);
