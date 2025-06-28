<?php
// Configuration de la base de données
define('DB_HOST', 'localhost:8889');
define('DB_NAME', 'ticket_system');
define('DB_USER', 'root');
define('DB_PASS', 'root');

// Configuration des chemins
define('UPLOAD_PATH', '../uploads/');
define('IMG_PATH', '../img/');
define('TICKET_TEMPLATES', [
    'jour1' => 'ticket_jour1.png',
    'jour2' => 'ticket_jour2.png',
    'jour3' => 'ticket_jour3.png'
]);

// Configuration QR Code
define('QR_SIZE', 150);
define('QR_POSITION_X', 2017);
define('QR_POSITION_Y', 17);

// Configuration PDF - Dimensions exactes du ticket
define('TICKET_WIDTH', 2711);  // Correction: 2711 au lieu de 3711
define('TICKET_HEIGHT', 202);

// Connexion à la base de données
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    die(json_encode([
        'success' => false,
        'message' => 'Erreur de connexion à la base de données: ' . $e->getMessage()
    ]));
}

// Fonction pour générer un UUID
function generateUUID() {
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

// Fonction pour créer les dossiers nécessaires
function createDirectories() {
    $dirs = [UPLOAD_PATH, IMG_PATH];
    foreach ($dirs as $dir) {
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}

// Headers CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

// Gestion des requêtes OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Créer les dossiers au démarrage
createDirectories();
?>