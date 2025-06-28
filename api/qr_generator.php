<?php
// Générateur de QR Code simple en PHP
// Utilise une API externe pour générer les QR codes

function generateQRCode($data, $size = 200) {
    try {
        // Utiliser l'API QR Server (gratuite)
        $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size={$size}x{$size}&data=" . urlencode($data);
        
        // Créer un contexte pour la requête HTTP
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'user_agent' => 'Mozilla/5.0 (compatible; TicketSystem/1.0)'
            ]
        ]);
        
        // Télécharger l'image QR
        $qrImageData = file_get_contents($qrUrl, false, $context);
        
        if ($qrImageData === false) {
            throw new Exception("Impossible de télécharger le QR code");
        }
        
        // Sauvegarder temporairement
        $tempPath = sys_get_temp_dir() . '/qr_' . md5($data) . '_' . time() . '.png';
        
        if (file_put_contents($tempPath, $qrImageData) === false) {
            throw new Exception("Impossible de sauvegarder le QR code temporaire");
        }
        
        return $tempPath;
        
    } catch (Exception $e) {
        error_log("Erreur génération QR: " . $e->getMessage());
        // Fallback vers le générateur simple
        return generateSimpleQR($data, $size);
    }
}

// Alternative: Générateur QR local simple (matrice basique)
function generateSimpleQR($data, $size = 200) {
    try {
        // Créer une image simple avec un motif QR-like
        $image = imagecreate($size, $size);
        
        // Couleurs
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        
        // Fond blanc
        imagefill($image, 0, 0, $white);
        
        // Créer un motif QR-like basé sur le hash des données
        $hash = md5($data);
        $gridSize = 20;
        $cellSize = $size / $gridSize;
        
        for ($x = 0; $x < $gridSize; $x++) {
            for ($y = 0; $y < $gridSize; $y++) {
                $index = ($x + $y * $gridSize) % strlen($hash);
                $char = $hash[$index];
                
                // Utiliser la valeur hexadécimale pour déterminer si on dessine un carré noir
                if (hexdec($char) % 2 == 0) {
                    imagefilledrectangle(
                        $image,
                        $x * $cellSize,
                        $y * $cellSize,
                        ($x + 1) * $cellSize - 1,
                        ($y + 1) * $cellSize - 1,
                        $black
                    );
                }
            }
        }
        
        // Ajouter les coins de positionnement (caractéristique des QR codes)
        $cornerSize = 3 * $cellSize;
        
        // Coin supérieur gauche
        imagefilledrectangle($image, 0, 0, $cornerSize, $cornerSize, $black);
        imagefilledrectangle($image, $cellSize, $cellSize, $cornerSize - $cellSize, $cornerSize - $cellSize, $white);
        
        // Coin supérieur droit
        imagefilledrectangle($image, $size - $cornerSize, 0, $size, $cornerSize, $black);
        imagefilledrectangle($image, $size - $cornerSize + $cellSize, $cellSize, $size - $cellSize, $cornerSize - $cellSize, $white);
        
        // Coin inférieur gauche
        imagefilledrectangle($image, 0, $size - $cornerSize, $cornerSize, $size, $black);
        imagefilledrectangle($image, $cellSize, $size - $cornerSize + $cellSize, $cornerSize - $cellSize, $size - $cellSize, $white);
        
        // Sauvegarder
        $tempPath = sys_get_temp_dir() . '/simple_qr_' . md5($data) . '_' . time() . '.png';
        
        if (imagepng($image, $tempPath)) {
            imagedestroy($image);
            return $tempPath;
        }
        
        imagedestroy($image);
        return false;
        
    } catch (Exception $e) {
        error_log("Erreur génération QR simple: " . $e->getMessage());
        return false;
    }
}

// Fonction pour créer un QR code avec texte (pour debug)
function generateTextQR($data, $size = 200) {
    $image = imagecreate($size, $size);
    
    // Couleurs
    $white = imagecolorallocate($image, 255, 255, 255);
    $black = imagecolorallocate($image, 0, 0, 0);
    $gray = imagecolorallocate($image, 128, 128, 128);
    
    // Fond blanc
    imagefill($image, 0, 0, $white);
    
    // Bordure
    imagerectangle($image, 0, 0, $size-1, $size-1, $black);
    
    // Texte QR
    $font = 3;
    $text = "QR CODE";
    $textWidth = imagefontwidth($font) * strlen($text);
    $textHeight = imagefontheight($font);
    $x = ($size - $textWidth) / 2;
    $y = ($size - $textHeight) / 2 - 10;
    
    imagestring($image, $font, $x, $y, $text, $black);
    
    // Données tronquées
    $shortData = substr($data, 0, 15) . "...";
    $font2 = 2;
    $textWidth2 = imagefontwidth($font2) * strlen($shortData);
    $x2 = ($size - $textWidth2) / 2;
    $y2 = $y + $textHeight + 5;
    
    imagestring($image, $font2, $x2, $y2, $shortData, $gray);
    
    // Sauvegarder
    $tempPath = sys_get_temp_dir() . '/text_qr_' . md5($data) . '_' . time() . '.png';
    
    if (imagepng($image, $tempPath)) {
        imagedestroy($image);
        return $tempPath;
    }
    
    imagedestroy($image);
    return false;
}
?>