<?php
require_once 'config.php';
require_once 'qr_generator.php';

// Vérifier la méthode HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit();
}

// Récupérer les données JSON
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['type']) || !isset($input['count'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Données manquantes']);
    exit();
}

$type = $input['type'];
$count = (int)$input['count'];
$generatePDF = isset($input['generate_pdf']) ? $input['generate_pdf'] : false;

// Validation
if (!in_array($type, ['jour1', 'jour2', 'jour3'])) {
    echo json_encode(['success' => false, 'message' => 'Type de ticket invalide']);
    exit();
}

if ($count < 1 || $count > 100) {
    echo json_encode(['success' => false, 'message' => 'Nombre de tickets invalide (1-100)']);
    exit();
}

// Vérifier que le template existe
$templatePath = IMG_PATH . TICKET_TEMPLATES[$type];
if (!file_exists($templatePath)) {
    // Créer un template de base si il n'existe pas
    createDefaultTemplate($templatePath, $type);
}

try {
    $generatedTickets = [];
    
    for ($i = 0; $i < $count; $i++) {
        // Générer un QR code unique
        $qrCode = generateUUID();
        
        // Créer l'image du ticket avec QR code
        $ticketImage = createTicketWithQR($templatePath, $qrCode, $type);
        
        if ($ticketImage) {
            // Sauvegarder le ticket dans la base de données
            $stmt = $pdo->prepare("INSERT INTO tickets (qr_code, type, image_path) VALUES (?, ?, ?)");
            $stmt->execute([$qrCode, $type, $ticketImage]);
            
            $ticketId = $pdo->lastInsertId();
            
            $generatedTickets[] = [
                'id' => $ticketId,
                'qr_code' => $qrCode,
                'type' => $type,
                'image_path' => $ticketImage
            ];
        } else {
            throw new Exception("Erreur lors de la création du ticket " . ($i + 1));
        }
    }
    
    // Si demandé, générer automatiquement le PDF avec mPDF
    if ($generatePDF) {
        $pdfPath = generateTicketsPDFWithMPDF($generatedTickets, $type);
        
        if ($pdfPath && file_exists($pdfPath)) {
            // Retourner les informations pour le téléchargement du PDF
            echo json_encode([
                'success' => true,
                'message' => $count . ' ticket(s) généré(s) avec succès',
                'tickets' => $generatedTickets,
                'pdf_generated' => true,
                'pdf_path' => $pdfPath,
                'pdf_filename' => 'tickets_' . $type . '_' . date('Y-m-d_H-i-s') . '.pdf'
            ]);
        } else {
            // PDF non généré mais tickets créés
            echo json_encode([
                'success' => true,
                'message' => $count . ' ticket(s) généré(s) avec succès (PDF non disponible)',
                'tickets' => $generatedTickets,
                'pdf_generated' => false
            ]);
        }
    } else {
        // Réponse normale sans PDF
        echo json_encode([
            'success' => true,
            'message' => $count . ' ticket(s) généré(s) avec succès',
            'tickets' => $generatedTickets,
            'pdf_generated' => false
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la génération: ' . $e->getMessage()
    ]);
}

function createDefaultTemplate($templatePath, $type) {
    // Créer un template par défaut avec les dimensions exactes
    $width = TICKET_WIDTH;  // 2711 pixels
    $height = TICKET_HEIGHT; // 202 pixels
    
    $image = imagecreatetruecolor($width, $height);
    
    // Couleurs
    $white = imagecolorallocate($image, 255, 255, 255);
    $blue = imagecolorallocate($image, 0, 123, 255);
    $black = imagecolorallocate($image, 0, 0, 0);
    $gray = imagecolorallocate($image, 200, 200, 200);
    
    // Fond blanc
    imagefill($image, 0, 0, $white);
    
    // Bordure bleue
    imagerectangle($image, 0, 0, $width-1, $height-1, $blue);
    imagerectangle($image, 10, 10, $width-11, $height-11, $blue);
    
    // Texte du ticket
    $font = 5;
    $text = "TICKET " . strtoupper($type);
    $x = 50;
    $y = ($height - imagefontheight($font)) / 2;
    
    imagestring($image, $font, $x, $y, $text, $black);
    
    // Informations supplémentaires
    $font2 = 3;
    $info = "Événement - " . date('Y');
    imagestring($image, $font2, $x, $y + 30, $info, $gray);
    
    // Zone pour QR code - ajuster la position pour les nouvelles dimensions
    $qrX = $width - QR_SIZE - 20; // Position à droite
    $qrY = ($height - QR_SIZE) / 2; // Centré verticalement
    $qrSize = QR_SIZE;
    
    imagerectangle($image, $qrX, $qrY, $qrX + $qrSize, $qrY + $qrSize, $black);
    imagestring($image, 2, $qrX + 10, $qrY + $qrSize/2 - 10, "QR CODE", $black);
    imagestring($image, 1, $qrX + 10, $qrY + $qrSize/2 + 5, "ZONE", $gray);
    
    // Sauvegarder
    if (!file_exists(dirname($templatePath))) {
        mkdir(dirname($templatePath), 0755, true);
    }
    
    imagepng($image, $templatePath);
    imagedestroy($image);
}

function createTicketWithQR($templatePath, $qrCode, $type) {
    try {
        // Charger l'image template
        $template = imagecreatefrompng($templatePath);
        if (!$template) {
            throw new Exception("Impossible de charger le template");
        }
        
        // Générer le QR code
        $qrCodePath = generateQRCode($qrCode);
        if (!$qrCodePath) {
            // Fallback: créer un QR code simple
            $qrCodePath = generateSimpleQR($qrCode);
        }
        
        if (!$qrCodePath) {
            throw new Exception("Impossible de générer le QR code");
        }
        
        // Charger l'image QR code
        $qrImage = imagecreatefrompng($qrCodePath);
        if (!$qrImage) {
            throw new Exception("Impossible de charger l'image QR code");
        }
        
        // Redimensionner le QR code si nécessaire
        $qrResized = imagecreatetruecolor(QR_SIZE, QR_SIZE);
        imagecopyresampled(
            $qrResized, $qrImage,
            0, 0, 0, 0,
            QR_SIZE, QR_SIZE,
            imagesx($qrImage), imagesy($qrImage)
        );
        
        // Position du QR code ajustée pour les nouvelles dimensions
        $qrX = TICKET_WIDTH - QR_SIZE - 342;
        $qrY = (TICKET_HEIGHT - QR_SIZE) / 2.8;
        
        // Ajouter le QR code sur le template
        imagecopy(
            $template, $qrResized,
            $qrX, $qrY,
            0, 0,
            QR_SIZE, QR_SIZE
        );
        
        // Sauvegarder l'image finale
        $filename = 'ticket_' . $type . '_' . time() . '_' . rand(1000, 9999) . '.png';
        $outputPath = UPLOAD_PATH . $filename;
        
        // Créer le dossier si nécessaire
        if (!file_exists(UPLOAD_PATH)) {
            mkdir(UPLOAD_PATH, 0755, true);
        }
        
        if (imagepng($template, $outputPath)) {
            // Nettoyer la mémoire
            imagedestroy($template);
            imagedestroy($qrImage);
            imagedestroy($qrResized);
            
            // Supprimer le fichier QR temporaire
            if (file_exists($qrCodePath)) {
                unlink($qrCodePath);
            }
            
            return $outputPath;
        } else {
            throw new Exception("Impossible de sauvegarder l'image finale");
        }
        
    } catch (Exception $e) {
        error_log("Erreur createTicketWithQR: " . $e->getMessage());
        return false;
    }
}

function generateTicketsPDFWithMPDF($tickets, $category) {
    try {
        // Augmenter les limites PHP
        ini_set('pcre.backtrack_limit', '10000000');
        ini_set('memory_limit', '1024M');
        ini_set('max_execution_time', '8600');
        
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
        $pageWidth = round($actualWidth / 96 * 25.4, 5);   // Largeur en mm
        $pageHeight = round($actualHeight / 96 * 25.4, 5); // Hauteur en mm
        
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
            'dpi' => 300,
            'img_dpi' => 300
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
            width: 2032.3pt; 
            height: 151.5pt; 
            overflow: hidden;
        }
        .ticket-image { 
            width: 2300.3pt!important; 
            height: 151.5pt!important; 
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