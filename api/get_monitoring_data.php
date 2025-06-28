<?php
require_once 'config.php';

try {
    // Statistiques générales
    $statsQuery = "
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN is_scanned = 1 THEN 1 ELSE 0 END) as scanned,
            SUM(CASE WHEN is_scanned = 0 THEN 1 ELSE 0 END) as pending
        FROM tickets
    ";
    
    $stmt = $pdo->query($statsQuery);
    $stats = $stmt->fetch();
    
    // Calculer le taux de scan
    $scanRate = $stats['total'] > 0 ? round(($stats['scanned'] / $stats['total']) * 100, 1) : 0;
    $stats['scan_rate'] = $scanRate;
    
    // Liste des tickets récents (50 derniers)
    $ticketsQuery = "
        SELECT 
            id,
            qr_code,
            type,
            is_scanned,
            DATE_FORMAT(created_at, '%d/%m/%Y %H:%i') as created_at,
            DATE_FORMAT(scanned_at, '%d/%m/%Y %H:%i') as scanned_at,
            image_path
        FROM tickets 
        ORDER BY created_at DESC 
        LIMIT 50
    ";
    
    $stmt = $pdo->query($ticketsQuery);
    $tickets = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'tickets' => $tickets
    ]);
    
} catch (Exception $e) {
    error_log("Erreur monitoring: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors du chargement des données'
    ]);
}
?>