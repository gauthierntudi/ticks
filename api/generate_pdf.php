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

if (!$input || !isset($input['category'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Catégorie manquante']);
    exit();
}

$category = $input['category'];

// Validation
if (!in_array($category, ['jour1', 'jour2', 'jour3'])) {
    echo json_encode(['success' => false, 'message' => 'Catégorie invalide']);
    exit();
}

try {
    // Récupérer tous les tickets de la catégorie avec leurs images
    $stmt = $pdo->prepare("SELECT id, qr_code, type, image_path FROM tickets WHERE type = ? ORDER BY created_at ASC");
    $stmt->execute([$category]);
    $tickets = $stmt->fetchAll();
    
    if (empty($tickets)) {
        echo json_encode(['success' => false, 'message' => 'Aucun ticket trouvé pour cette catégorie']);
        exit();
    }
    
    // Vérifier que les fichiers d'images existent
    $validTickets = [];
    foreach ($tickets as $ticket) {
        if (file_exists($ticket['image_path'])) {
            $validTickets[] = $ticket;
        } else {
            error_log("Fichier ticket non trouvé: " . $ticket['image_path']);
        }
    }
    
    if (empty($validTickets)) {
        echo json_encode(['success' => false, 'message' => 'Aucun fichier de ticket valide trouvé']);
        exit();
    }
    
    // Générer le PDF avec mPDF
    $pdfPath = generateTicketsPDFWithMPDF($validTickets, $category);
    
    if ($pdfPath && file_exists($pdfPath)) {
        // Envoyer le fichier PDF
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="tickets_' . $category . '_' . date('Y-m-d_H-i-s') . '.pdf"');
        header('Content-Length: ' . filesize($pdfPath));
        
        readfile($pdfPath);
        
        // Supprimer le fichier temporaire
        unlink($pdfPath);
        
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la génération du PDF']);
    }
    
} catch (Exception $e) {
    error_log("Erreur génération PDF: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}

function generateTicketsPDFWithMPDF($tickets, $category) {
    try {
        // Augmenter les limites PHP
        ini_set('pcre.backtrack_limit', '10000000');
        ini_set('memory_limit', '1024M');
        ini_set('max_execution_time', '600');
        
        // Vérifier si Composer autoload existe
        $autoloadPath = __DIR__ . '/../vendor/autoload.php';
        if (!file_exists($autoloadPath)) {
            error_log("Composer autoload non trouvé: " . $autoloadPath);
            return createFallbackPDF($tickets, $category);
        }
        
        require_once $autoloadPath;
        
        // OBTENIR LES DIMENSIONS RÉELLES DE L'IMAGE DU PREMIER TICKET
        $firstTicketPath = $tickets[0]['image_path'];
        if (!file_exists($firstTicketPath)) {
            throw new Exception("Premier ticket non trouvé pour mesurer les dimensions");
        }
        
        // Lire les dimensions réelles de l'image
        $imageInfo = getimagesize($firstTicketPath);
        if (!$imageInfo) {
            throw new Exception("Impossible de lire les dimensions de l'image");
        }
        
        $actualWidth = $imageInfo[0];  // Largeur réelle en pixels
        $actualHeight = $imageInfo[1]; // Hauteur réelle en pixels
        
        error_log("Dimensions réelles de l'image: {$actualWidth}x{$actualHeight} pixels");
        
        // Calculer les dimensions PDF basées sur les dimensions RÉELLES de l'image
        // Conversion: pixels à 96 DPI vers millimètres (1 inch = 25.4 mm)
        $pageWidth = round($actualWidth / 96 * 25.4, 2);   // Largeur en mm
        $pageHeight = round($actualHeight / 96 * 25.4, 2); // Hauteur en mm
        
        error_log("Dimensions PDF calculées: {$pageWidth}x{$pageHeight} mm");
        
        // Créer une instance mPDF avec les dimensions exactes de l'image
        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => [$pageWidth, $pageHeight], // Format basé sur les dimensions réelles
            'margin_left' => 0,
            'margin_right' => 0,
            'margin_top' => 0,
            'margin_bottom' => 0,
            'margin_header' => 0,
            'margin_footer' => 0,
            'tempDir' => sys_get_temp_dir(),
            'dpi' => 96,
            'img_dpi' => 96
        ]);
        
        // Configuration du PDF
        $mpdf->SetTitle('Tickets ' . strtoupper($category));
        $mpdf->SetAuthor('Système de Gestion de Tickets');
        $mpdf->SetCreator('mPDF Ticket Generator');
        
        // CSS pour afficher l'image aux dimensions exactes SANS déformation
        $css = '
        body { 
            margin: 0; 
            padding: 0; 
            width: 100%; 
            height: 100%; 
            overflow: hidden;
        }
        .ticket-image { 
            width: 100%; 
            height: 100%; 
            object-fit: fill; 
            display: block; 
            margin: 0;
            padding: 0;
            border: none;
        }
        ';
        
        $mpdf->WriteHTML($css, \Mpdf\HTMLParserMode::HEADER_CSS);
        
        // Traiter chaque ticket individuellement
        foreach ($tickets as $index => $ticket) {
            if (!file_exists($ticket['image_path'])) {
                error_log("Fichier ticket non trouvé: " . $ticket['image_path']);
                continue;
            }
            
            // Ajouter une nouvelle page sauf pour le premier ticket
            if ($index > 0) {
                $mpdf->AddPage();
            }
            
            // Lire et encoder l'image du ticket
            $imageData = base64_encode(file_get_contents($ticket['image_path']));
            
            // HTML ultra-minimal : juste l'image du ticket
            $html = '<img src="data:image/png;base64,' . $imageData . '" class="ticket-image" alt="Ticket">';
            
            // Écrire le HTML
            $mpdf->WriteHTML($html);
        }
        
        // Sauvegarder le PDF
        $pdfPath = sys_get_temp_dir() . '/tickets_exact_' . $category . '_' . time() . '.pdf';
        $mpdf->Output($pdfPath, \Mpdf\Output\Destination::FILE);
        
        error_log("PDF généré avec succès: " . $pdfPath);
        return $pdfPath;
        
    } catch (Exception $e) {
        error_log("Erreur mPDF: " . $e->getMessage());
        return createFallbackPDF($tickets, $category);
    }
}

function createFallbackPDF($tickets, $category) {
    $tempPdfPath = sys_get_temp_dir() . '/fallback_pdf_' . $category . '_' . time() . '.pdf';
    
    // Utiliser les dimensions réelles si possible
    $pageWidth = 2032.3;  // Fallback basé sur TICKET_WIDTH
    $pageHeight = 151.5;  // Fallback basé sur TICKET_HEIGHT
    
    // Essayer de lire les dimensions du premier ticket
    if (!empty($tickets) && file_exists($tickets[0]['image_path'])) {
        $imageInfo = getimagesize($tickets[0]['image_path']);
        if ($imageInfo) {
            $pageWidth = round($imageInfo[0] / 96 * 72, 1);
            $pageHeight = round($imageInfo[1] / 96 * 72, 1);
        }
    }
    
    $pdfContent = "%PDF-1.4
1 0 obj
<<
/Type /Catalog
/Pages 2 0 R
>>
endobj

2 0 obj
<<
/Type /Pages
/Kids [3 0 R]
/Count 1
>>
endobj

3 0 obj
<<
/Type /Page
/Parent 2 0 R
/MediaBox [0 0 $pageWidth $pageHeight]
/Contents 4 0 R
/Resources <<
/Font <<
/F1 <<
/Type /Font
/Subtype /Type1
/BaseFont /Helvetica
>>
/F2 <<
/Type /Font
/Subtype /Type1
/BaseFont /Helvetica-Bold
>>
>>
>>
>>
endobj

4 0 obj
<<
/Length 400
>>
stream
BT
/F2 8 Tf
10 " . ($pageHeight - 15) . " Td
(TICKETS " . strtoupper($category) . " - ERREUR mPDF) Tj
0 -12 Td
/F1 6 Tf
(PDF: " . $pageWidth . "x" . $pageHeight . " points) Tj
0 -10 Td
(Tickets: " . count($tickets) . ") Tj
0 -10 Td
(Installez mPDF: composer require mpdf/mpdf) Tj
0 -12 Td
(Les images des tickets sont dans uploads/) Tj
ET
endstream
endobj

xref
0 5
0000000000 65535 f 
0000000009 00000 n 
0000000058 00000 n 
0000000115 00000 n 
0000000300 00000 n 
trailer
<<
/Size 5
/Root 1 0 R
>>
startxref
800
%%EOF";
    
    file_put_contents($tempPdfPath, $pdfContent);
    return $tempPdfPath;
}
?>