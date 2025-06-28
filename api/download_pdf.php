<?php
require_once 'config.php';

// Vérifier la méthode HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit();
}

// Récupérer les données JSON
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['pdf_path'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Chemin PDF manquant']);
    exit();
}

$pdfPath = $input['pdf_path'];

// Vérifier que le fichier existe et est dans le dossier temporaire
if (!file_exists($pdfPath) || strpos($pdfPath, sys_get_temp_dir()) !== 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Fichier PDF non trouvé ou non autorisé']);
    exit();
}

try {
    // Envoyer le fichier PDF
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . basename($pdfPath) . '"');
    header('Content-Length: ' . filesize($pdfPath));
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: 0');
    
    // Lire et envoyer le fichier
    readfile($pdfPath);
    
    // Supprimer le fichier temporaire après envoi
    unlink($pdfPath);
    
} catch (Exception $e) {
    error_log("Erreur téléchargement PDF: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur lors du téléchargement']);
}
?>