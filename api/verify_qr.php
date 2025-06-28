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

if (!$input || !isset($input['qr_code'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'QR code manquant']);
    exit();
}

$qrCode = trim($input['qr_code']);

if (empty($qrCode)) {
    echo json_encode(['success' => false, 'message' => 'QR code vide']);
    exit();
}

try {
    // Rechercher le ticket dans la base de données
    $stmt = $pdo->prepare("SELECT * FROM tickets WHERE qr_code = ?");
    $stmt->execute([$qrCode]);
    $ticket = $stmt->fetch();
    
    if (!$ticket) {
        echo json_encode([
            'success' => false,
            'message' => 'QR code non trouvé dans la base de données',
            'ticket' => null
        ]);
        exit();
    }
    
    // Vérifier si le ticket a déjà été scanné
    if ($ticket['is_scanned']) {
        echo json_encode([
            'success' => true,
            'message' => 'Attention: Ce ticket a déjà été utilisé le ' . $ticket['scanned_at'],
            'ticket' => $ticket,
            'already_scanned' => true
        ]);
    } else {
        // Marquer le ticket comme scanné
        $updateStmt = $pdo->prepare("UPDATE tickets SET is_scanned = TRUE, scanned_at = NOW() WHERE id = ?");
        $updateStmt->execute([$ticket['id']]);
        
        // Enregistrer le log de scan
        $logStmt = $pdo->prepare("INSERT INTO scan_logs (ticket_id, ip_address, user_agent) VALUES (?, ?, ?)");
        $logStmt->execute([
            $ticket['id'],
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
        
        // Récupérer les données mises à jour
        $stmt->execute([$qrCode]);
        $updatedTicket = $stmt->fetch();
        
        echo json_encode([
            'success' => true,
            'message' => 'Ticket valide et maintenant marqué comme utilisé',
            'ticket' => $updatedTicket,
            'already_scanned' => false
        ]);
    }
    
} catch (Exception $e) {
    error_log("Erreur vérification QR: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur serveur lors de la vérification'
    ]);
}
?>