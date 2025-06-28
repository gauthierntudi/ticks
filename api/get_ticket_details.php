<?php
require_once 'config.php';

// Vérifier la méthode HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit();
}

$ticketId = $_GET['id'] ?? null;

if (!$ticketId || !is_numeric($ticketId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de ticket invalide']);
    exit();
}

try {
    // Récupérer les détails du ticket
    $stmt = $pdo->prepare("
        SELECT 
            id,
            qr_code,
            type,
            image_path,
            is_scanned,
            DATE_FORMAT(created_at, '%d/%m/%Y à %H:%i:%s') as created_at,
            DATE_FORMAT(scanned_at, '%d/%m/%Y à %H:%i:%s') as scanned_at
        FROM tickets 
        WHERE id = ?
    ");
    
    $stmt->execute([$ticketId]);
    $ticket = $stmt->fetch();
    
    if (!$ticket) {
        echo json_encode([
            'success' => false,
            'message' => 'Ticket non trouvé'
        ]);
        exit();
    }
    
    // Récupérer l'historique des scans
    $logStmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(scan_time, '%d/%m/%Y à %H:%i:%s') as scan_time,
            ip_address
        FROM scan_logs 
        WHERE ticket_id = ? 
        ORDER BY scan_time DESC
    ");
    
    $logStmt->execute([$ticketId]);
    $scanHistory = $logStmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'ticket' => $ticket,
        'scan_history' => $scanHistory
    ]);
    
} catch (Exception $e) {
    error_log("Erreur détails ticket: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors du chargement des détails'
    ]);
}
?>