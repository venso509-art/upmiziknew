<?php
/**
 * UpMizik - Settings API Endpoint (Hostinger / MySQL / configurations table)
 * 
 * Jere konfigirasyon dinamik platfòm nan:
 * - Pri enskripsyon atis (USD & HTG)
 * - Taux echanj dola (USD ↔ HTG)
 * - Mwayen peman ak nimewo telefòn MonCash / Natcash / Zelle
 * - Notis jeneral ak enstriksyon peman
 */

require_once dirname(__DIR__) . '/middleware/cors.php';
require_once dirname(__DIR__) . '/config/database.php';

$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

// Asire tab `configurations` la egziste
$pdo->exec("
    CREATE TABLE IF NOT EXISTS `configurations` (
        `cle` VARCHAR(64) NOT NULL PRIMARY KEY,
        `valeur` LONGTEXT NOT NULL,
        `date_mise_a_jour` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// ----------------------------------------------------------
// 1. GET: Rekipere dènye paramèt peman ak frè enskripsyon yo
// ----------------------------------------------------------
if ($method === 'GET') {
    try {
        $stmt = $pdo->prepare("SELECT valeur, date_mise_a_jour FROM configurations WHERE cle = 'payment_settings'");
        $stmt->execute();
        $row = $stmt->fetch();

        if ($row && !empty($row['valeur'])) {
            $settings = json_decode($row['valeur'], true);
            if (is_array($settings)) {
                $settings['updatedAt'] = $row['date_mise_a_jour'];
                jsonResponse([
                    'success' => true,
                    'settings' => $settings,
                    'source' => 'mysql',
                    'updatedAt' => $row['date_mise_a_jour']
                ]);
            }
        }

        // Si pa genyen nan baz done a ankò, voye null pou kliyan an itilize default
        jsonResponse([
            'success' => true,
            'settings' => null,
            'message' => 'Pa gen konfigirasyon espesifik ki anrejistre nan baz done a ankò.'
        ]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => 'Erè lekti paramèt: ' . $e->getMessage()], 500);
    }
}

// ----------------------------------------------------------
// 2. POST / PUT: Anrejistre oswa mete ajou paramèt peman yo
// ----------------------------------------------------------
if ($method === 'POST' || $method === 'PUT') {
    try {
        $data = getJsonInput();
        if (empty($data)) {
            jsonResponse(['success' => false, 'message' => 'Done JSON vid oswa envalid.'], 400);
        }

        // Sipòte swa `{ settings: {...} }` oswa dirèkteman objè konfigirasyon an
        $settingsToSave = isset($data['settings']) && is_array($data['settings']) ? $data['settings'] : $data;

        // Validasyon debaz
        if (isset($settingsToSave['artistRegistrationFeeUsd'])) {
            $settingsToSave['artistRegistrationFeeUsd'] = (float)$settingsToSave['artistRegistrationFeeUsd'];
        }
        if (isset($settingsToSave['htgExchangeRate'])) {
            $settingsToSave['htgExchangeRate'] = (float)$settingsToSave['htgExchangeRate'];
        }
        if (isset($settingsToSave['artistRegistrationFeeHtg'])) {
            $settingsToSave['artistRegistrationFeeHtg'] = (float)$settingsToSave['artistRegistrationFeeHtg'];
        }

        $settingsToSave['updatedAt'] = date('Y-m-d H:i:s');
        $json = json_encode($settingsToSave, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $stmt = $pdo->prepare("
            INSERT INTO configurations (cle, valeur)
            VALUES ('payment_settings', ?)
            ON DUPLICATE KEY UPDATE 
                valeur = VALUES(valeur),
                date_mise_a_jour = CURRENT_TIMESTAMP
        ");
        $stmt->execute([$json]);

        jsonResponse([
            'success' => true,
            'message' => 'Paramèt peman ak nimewo yo anrejistre avèk siksè nan baz done MySQL la!',
            'settings' => $settingsToSave,
            'updatedAt' => $settingsToSave['updatedAt']
        ]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => 'Erè anrejistreman paramèt: ' . $e->getMessage()], 500);
    }
}

jsonResponse(['success' => false, 'message' => 'Metòd HTTP sa a pa sipòte.'], 405);
