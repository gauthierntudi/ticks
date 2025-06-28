-- Base de données pour le système de tickets
CREATE DATABASE IF NOT EXISTS ticket_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE ticket_system;

-- Table des tickets
CREATE TABLE IF NOT EXISTS tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    qr_code VARCHAR(255) UNIQUE NOT NULL,
    type ENUM('jour1', 'jour2', 'jour3') NOT NULL,
    image_path VARCHAR(500) NOT NULL,
    is_scanned BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    scanned_at TIMESTAMP NULL,
    INDEX idx_qr_code (qr_code),
    INDEX idx_type (type),
    INDEX idx_scanned (is_scanned),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB;

-- Table des logs de scan (optionnel pour l'historique)
CREATE TABLE IF NOT EXISTS scan_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    scan_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    user_agent TEXT,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    INDEX idx_ticket_id (ticket_id),
    INDEX idx_scan_time (scan_time)
) ENGINE=InnoDB;

-- Insertion de données de test (optionnel)
-- INSERT INTO tickets (qr_code, type, image_path) VALUES 
-- ('TEST-QR-001', 'jour1', 'uploads/ticket_1.png'),
-- ('TEST-QR-002', 'jour2', 'uploads/ticket_2.png'),
-- ('TEST-QR-003', 'jour3', 'uploads/ticket_3.png');