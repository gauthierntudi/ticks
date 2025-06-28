// Configuration globale
const CONFIG = {
    apiUrl: 'api/',
    ticketSize: {
        width: 3711,
        height: 276
    },
    qrCodeSize: 100,
    qrCodePosition: {
        x: 3500,
        y: 88
    }
};

// Variables globales
let html5QrCode = null;
let isScanning = false;

// Initialisation de l'application
document.addEventListener('DOMContentLoaded', function() {
    initializeApp();
    loadMonitoringData();
    setupEventListeners();
    // Retarder les animations pour éviter les conflits d'affichage
    setTimeout(initializeAnimations, 500);
});

// Initialisation de l'application
function initializeApp() {
    console.log('Initialisation de l\'application...');
    
    // Animation d'entrée de la navbar seulement
    gsap.from('.navbar', {
        duration: 1,
        y: -100,
        opacity: 0,
        ease: 'bounce.out'
    });
    
    // Ne pas animer les cartes au chargement pour éviter les problèmes d'affichage
    // Les cartes restent visibles par défaut
}

// Configuration des écouteurs d'événements
function setupEventListeners() {
    // Boutons de scanner
    document.getElementById('start-scan').addEventListener('click', startScanning);
    document.getElementById('stop-scan').addEventListener('click', stopScanning);
    
    // Actualisation automatique du monitoring
    setInterval(loadMonitoringData, 30000); // Toutes les 30 secondes
}

// Initialisation des animations GSAP
function initializeAnimations() {
    // Ne pas masquer les cartes de statistiques par défaut
    // gsap.set('.stat-card', { scale: 0.8, opacity: 0 }); // Commenté pour éviter les problèmes
    
    // Vérifier si ScrollTrigger est disponible avant de l'utiliser
    if (typeof ScrollTrigger !== 'undefined') {
        gsap.registerPlugin(ScrollTrigger);
        
        // Animation des éléments au scroll (seulement pour les nouveaux éléments)
        gsap.utils.toArray('.gsap-fade-in').forEach(element => {
            // Vérifier si l'élément n'est pas déjà visible
            if (!element.classList.contains('visible')) {
                gsap.from(element, {
                    scrollTrigger: element,
                    duration: 1,
                    y: 50,
                    opacity: 0,
                    ease: 'power2.out',
                    onComplete: () => element.classList.add('visible')
                });
            }
        });
    } else {
        console.warn('ScrollTrigger non disponible, animations de scroll désactivées');
    }
}

// Gestion des sections
function showSection(sectionName) {
    // Masquer toutes les sections
    document.querySelectorAll('.section').forEach(section => {
        section.classList.remove('active');
        gsap.to(section, {
            duration: 0.3,
            opacity: 0,
            y: 20,
            display: 'none'
        });
    });
    
    // Afficher la section demandée
    const targetSection = document.getElementById(sectionName + '-section');
    if (targetSection) {
        setTimeout(() => {
            targetSection.classList.add('active');
            gsap.fromTo(targetSection, 
                { opacity: 0, y: 20, display: 'block' },
                { duration: 0.5, opacity: 1, y: 0, ease: 'power2.out' }
            );
            
            // Charger les données spécifiques à la section
            if (sectionName === 'monitoring') {
                loadMonitoringData();
                // Animation des stats seulement si on va sur monitoring
                setTimeout(animateStats, 200);
            }
        }, 300);
    }
}

// Animation des statistiques (seulement quand on va sur monitoring)
function animateStats() {
    // Animation douce des cartes de stats
    gsap.fromTo('.stat-card', 
        { scale: 0.9, opacity: 0.7 },
        {
            duration: 0.6,
            scale: 1,
            opacity: 1,
            stagger: 0.1,
            ease: 'back.out(1.2)'
        }
    );
    
    // Animation des chiffres
    document.querySelectorAll('.stat-number').forEach(element => {
        const finalValue = parseInt(element.textContent) || 0;
        if (finalValue > 0) {
            gsap.from({ value: 0 }, {
                duration: 2,
                value: finalValue,
                ease: 'power2.out',
                onUpdate: function() {
                    element.textContent = Math.round(this.targets()[0].value);
                }
            });
        }
    });
}

// Génération des tickets avec PDF automatique
async function generateTickets() {
    const ticketType = document.getElementById('ticketType').value;
    const ticketCount = parseInt(document.getElementById('ticketCount').value);
    
    if (ticketCount < 1 || ticketCount > 100) {
        showAlert('Veuillez entrer un nombre de tickets entre 1 et 100', 'warning');
        return;
    }
    
    showLoading(true);
    
    try {
        const response = await fetch(CONFIG.apiUrl + 'generate_tickets.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                type: ticketType,
                count: ticketCount,
                generate_pdf: true // Demander la génération automatique du PDF
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            displayTicketPreviews(result.tickets);
            showAlert(`${ticketCount} ticket(s) généré(s) avec succès!`, 'success');
            
            // Si un PDF a été généré, le télécharger automatiquement
            if (result.pdf_generated && result.pdf_path) {
                setTimeout(() => {
                    downloadGeneratedPDF(result.pdf_path, result.pdf_filename);
                }, 1000);
            } else if (result.pdf_generated === false) {
                showAlert('Tickets générés mais PDF non disponible (vérifiez la configuration du serveur)', 'warning');
            }
        } else {
            showAlert('Erreur lors de la génération: ' + result.message, 'error');
        }
    } catch (error) {
        console.error('Erreur:', error);
        showAlert('Erreur de connexion au serveur', 'error');
    } finally {
        showLoading(false);
    }
}

// Téléchargement du PDF généré
async function downloadGeneratedPDF(pdfPath, filename) {
    try {
        showAlert('Téléchargement du PDF en cours...', 'info');
        
        // Créer une requête pour télécharger le fichier
        const response = await fetch(CONFIG.apiUrl + 'download_pdf.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                pdf_path: pdfPath
            })
        });
        
        if (response.ok) {
            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            
            showAlert('PDF téléchargé avec succès!', 'success');
        } else {
            showAlert('Erreur lors du téléchargement du PDF', 'error');
        }
    } catch (error) {
        console.error('Erreur téléchargement PDF:', error);
        showAlert('Erreur lors du téléchargement du PDF', 'error');
    }
}

// Affichage des aperçus de tickets
function displayTicketPreviews(tickets) {
    const container = document.getElementById('ticketPreview');
    container.innerHTML = '';
    
    tickets.forEach((ticket, index) => {
        const ticketDiv = document.createElement('div');
        ticketDiv.className = 'ticket-preview';
        ticketDiv.innerHTML = `
            <img src="${ticket.image_path}" alt="Ticket ${ticket.type}" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjUwIiB2aWV3Qm94PSIwIDAgMjAwIDUwIiBmaWxsPSJub25lIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciPgo8cmVjdCB3aWR0aD0iMjAwIiBoZWlnaHQ9IjUwIiBmaWxsPSIjZjBmMGYwIiBzdHJva2U9IiNkZGQiLz4KPHRleHQgeD0iMTAwIiB5PSIzMCIgZm9udC1mYW1pbHk9IkFyaWFsIiBmb250LXNpemU9IjEyIiBmaWxsPSIjNjY2IiB0ZXh0LWFuY2hvcj0ibWlkZGxlIj5UaWNrZXQgJHt0aWNrZXQudHlwZX08L3RleHQ+Cjwvc3ZnPg=='">
            <div class="ticket-info">
                <strong>ID:</strong> ${ticket.id}<br>
                <strong>QR:</strong> ${ticket.qr_code.substring(0, 10)}...
            </div>
        `;
        container.appendChild(ticketDiv);
    });
    
    // Animation d'apparition des nouveaux tickets
    gsap.from('.ticket-preview', {
        duration: 0.8,
        scale: 0,
        opacity: 0,
        stagger: 0.1,
        ease: 'back.out(1.7)'
    });
}

// Génération de PDF (fonction existante pour le bouton séparé)
async function generatePDF() {
    const category = document.getElementById('pdfCategory').value;
    
    showLoading(true);
    
    try {
        const response = await fetch(CONFIG.apiUrl + 'generate_pdf.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                category: category
            })
        });
        
        if (response.ok) {
            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `tickets_${category}_${new Date().getTime()}.pdf`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            
            showAlert('PDF généré et téléchargé avec succès!', 'success');
        } else {
            const errorData = await response.json();
            showAlert('Erreur lors de la génération du PDF: ' + (errorData.message || 'Erreur inconnue'), 'error');
        }
    } catch (error) {
        console.error('Erreur:', error);
        showAlert('Erreur de connexion au serveur', 'error');
    } finally {
        showLoading(false);
    }
}

// Démarrage du scanner QR
function startScanning() {
    const qrReaderElement = document.getElementById('qr-reader');
    
    if (!html5QrCode) {
        html5QrCode = new Html5Qrcode("qr-reader");
    }
    
    const config = {
        fps: 10,
        qrbox: { width: 250, height: 250 },
        aspectRatio: 1.0
    };
    
    html5QrCode.start(
        { facingMode: "environment" },
        config,
        onScanSuccess,
        onScanFailure
    ).then(() => {
        isScanning = true;
        document.getElementById('start-scan').style.display = 'none';
        document.getElementById('stop-scan').style.display = 'inline-block';
        
        // Animation du scanner
        gsap.to('#qr-reader', {
            duration: 0.5,
            scale: 1.05,
            ease: 'power2.out'
        });
    }).catch(err => {
        console.error('Erreur de démarrage du scanner:', err);
        showAlert('Impossible de démarrer la caméra', 'error');
    });
}

// Arrêt du scanner QR
function stopScanning() {
    if (html5QrCode && isScanning) {
        html5QrCode.stop().then(() => {
            isScanning = false;
            document.getElementById('start-scan').style.display = 'inline-block';
            document.getElementById('stop-scan').style.display = 'none';
            
            gsap.to('#qr-reader', {
                duration: 0.5,
                scale: 1,
                ease: 'power2.out'
            });
        }).catch(err => {
            console.error('Erreur d\'arrêt du scanner:', err);
        });
    }
}

// Succès du scan QR
async function onScanSuccess(decodedText, decodedResult) {
    console.log('QR Code scanné:', decodedText);
    
    // Arrêter le scanner temporairement
    if (isScanning) {
        await html5QrCode.pause();
    }
    
    // Vérifier le QR code dans la base de données
    try {
        const response = await fetch(CONFIG.apiUrl + 'verify_qr.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                qr_code: decodedText
            })
        });
        
        const result = await response.json();
        displayScanResult(result, decodedText);
        
    } catch (error) {
        console.error('Erreur de vérification:', error);
        displayScanResult({
            success: false,
            message: 'Erreur de connexion au serveur'
        }, decodedText);
    }
    
    // Reprendre le scanner après 3 secondes
    setTimeout(() => {
        if (html5QrCode && isScanning) {
            html5QrCode.resume();
        }
    }, 3000);
}

// Échec du scan QR
function onScanFailure(error) {
    // Ne pas afficher les erreurs de scan en continu
}

// Affichage du résultat du scan
function displayScanResult(result, qrCode) {
    const resultDiv = document.getElementById('scan-result');
    const modalBody = document.getElementById('scanModalBody');
    
    let statusClass, statusIcon, statusText, statusColor;
    
    if (result.success) {
        if (result.already_scanned) {
            statusClass = 'scan-warning';
            statusIcon = 'fas fa-exclamation-triangle';
            statusText = 'Ticket déjà scanné';
            statusColor = 'warning';
        } else {
            statusClass = 'scan-success';
            statusIcon = 'fas fa-check-circle';
            statusText = 'Ticket valide';
            statusColor = 'success';
        }
    } else {
        statusClass = 'scan-error';
        statusIcon = 'fas fa-times-circle';
        statusText = 'Ticket invalide';
        statusColor = 'danger';
    }
    
    const resultHTML = `
        <div class="${statusClass}">
            <div class="text-center">
                <i class="${statusIcon} fa-3x mb-3"></i>
                <h4>${statusText}</h4>
                <p><strong>QR Code:</strong> ${qrCode}</p>
                ${result.ticket ? `
                    <p><strong>Type:</strong> ${result.ticket.type}</p>
                    <p><strong>Date création:</strong> ${result.ticket.created_at}</p>
                    ${result.ticket.scanned_at ? `<p><strong>Première utilisation:</strong> ${result.ticket.scanned_at}</p>` : ''}
                ` : ''}
                <p class="mt-3">${result.message}</p>
            </div>
        </div>
    `;
    
    resultDiv.innerHTML = resultHTML;
    modalBody.innerHTML = resultHTML;
    
    // Animation du résultat
    gsap.from(resultDiv.firstElementChild, {
        duration: 0.6,
        scale: 0.8,
        opacity: 0,
        ease: 'back.out(1.7)'
    });
    
    // Afficher la modal
    const modal = new bootstrap.Modal(document.getElementById('scanResultModal'));
    modal.show();
    
    // Actualiser les données de monitoring
    loadMonitoringData();
}

// Chargement des données de monitoring
async function loadMonitoringData() {
    try {
        const response = await fetch(CONFIG.apiUrl + 'get_monitoring_data.php');
        const data = await response.json();
        
        if (data.success) {
            updateMonitoringStats(data.stats);
            updateMonitoringTable(data.tickets);
        }
    } catch (error) {
        console.error('Erreur de chargement des données:', error);
    }
}

// Mise à jour des statistiques
function updateMonitoringStats(stats) {
    document.getElementById('total-tickets').textContent = stats.total || 0;
    document.getElementById('scanned-tickets').textContent = stats.scanned || 0;
    document.getElementById('pending-tickets').textContent = stats.pending || 0;
    document.getElementById('scan-rate').textContent = (stats.scan_rate || 0) + '%';
}

// Mise à jour du tableau de monitoring
function updateMonitoringTable(tickets) {
    const tbody = document.getElementById('tickets-table');
    tbody.innerHTML = '';
    
    if (!tickets || tickets.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">Aucun ticket trouvé</td></tr>';
        return;
    }
    
    tickets.forEach(ticket => {
        const row = document.createElement('tr');
        row.className = 'gsap-fade-in';
        
        const statusBadge = ticket.is_scanned 
            ? '<span class="badge bg-success">Scanné</span>'
            : '<span class="badge bg-warning">En attente</span>';
        
        row.innerHTML = `
            <td>${ticket.id}</td>
            <td><code>${ticket.qr_code.substring(0, 15)}...</code></td>
            <td><span class="badge bg-info">${ticket.type}</span></td>
            <td>${statusBadge}</td>
            <td>${ticket.created_at}</td>
            <td>${ticket.scanned_at || '-'}</td>
            <td>
                <button class="btn btn-sm btn-outline-primary" onclick="viewTicketDetails(${ticket.id})">
                    <i class="fas fa-eye"></i>
                </button>
            </td>
        `;
        
        tbody.appendChild(row);
    });
    
    // Animation des lignes du tableau (plus douce)
    gsap.from('#tickets-table tr', {
        duration: 0.3,
        x: -30,
        opacity: 0,
        stagger: 0.03,
        ease: 'power2.out'
    });
}

// Affichage des détails d'un ticket
async function viewTicketDetails(ticketId) {
    try {
        const response = await fetch(CONFIG.apiUrl + 'get_ticket_details.php?id=' + ticketId);
        const result = await response.json();
        
        if (result.success) {
            const ticket = result.ticket;
            const modalBody = document.getElementById('scanModalBody');
            
            modalBody.innerHTML = `
                <div class="text-center">
                    <h5>Détails du Ticket #${ticket.id}</h5>
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <img src="${ticket.image_path}" class="img-fluid rounded" alt="Ticket" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjUwIiB2aWV3Qm94PSIwIDAgMjAwIDUwIiBmaWxsPSJub25lIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciPgo8cmVjdCB3aWR0aD0iMjAwIiBoZWlnaHQ9IjUwIiBmaWxsPSIjZjBmMGYwIiBzdHJva2U9IiNkZGQiLz4KPHRleHQgeD0iMTAwIiB5PSIzMCIgZm9udC1mYW1pbHk9IkFyaWFsIiBmb250LXNpemU9IjEyIiBmaWxsPSIjNjY2IiB0ZXh0LWFuY2hvcj0ibWlkZGxlIj5UaWNrZXQgJHt0aWNrZXQudHlwZX08L3RleHQ+Cjwvc3ZnPg=='">
                        </div>
                        <div class="col-md-6">
                            <table class="table table-sm">
                                <tr><td><strong>Type:</strong></td><td>${ticket.type}</td></tr>
                                <tr><td><strong>QR Code:</strong></td><td><code>${ticket.qr_code}</code></td></tr>
                                <tr><td><strong>Statut:</strong></td><td>${ticket.is_scanned ? '<span class="badge bg-success">Scanné</span>' : '<span class="badge bg-warning">En attente</span>'}</td></tr>
                                <tr><td><strong>Créé le:</strong></td><td>${ticket.created_at}</td></tr>
                                <tr><td><strong>Scanné le:</strong></td><td>${ticket.scanned_at || 'Jamais'}</td></tr>
                            </table>
                        </div>
                    </div>
                </div>
            `;
            
            const modal = new bootstrap.Modal(document.getElementById('scanResultModal'));
            modal.show();
        }
    } catch (error) {
        console.error('Erreur:', error);
        showAlert('Erreur lors du chargement des détails', 'error');
    }
}

// Affichage des alertes
function showAlert(message, type) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show position-fixed`;
    alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(alertDiv);
    
    // Animation d'entrée
    gsap.from(alertDiv, {
        duration: 0.5,
        x: 300,
        opacity: 0,
        ease: 'back.out(1.7)'
    });
    
    // Suppression automatique après 5 secondes
    setTimeout(() => {
        if (alertDiv.parentNode) {
            gsap.to(alertDiv, {
                duration: 0.3,
                x: 300,
                opacity: 0,
                ease: 'power2.in',
                onComplete: () => {
                    if (alertDiv.parentNode) {
                        alertDiv.parentNode.removeChild(alertDiv);
                    }
                }
            });
        }
    }, 5000);
}

// Affichage/masquage du loading
function showLoading(show) {
    const overlay = document.getElementById('loading-overlay');
    if (show) {
        overlay.classList.add('show');
        gsap.from('.loading-spinner', {
            duration: 0.5,
            scale: 0.5,
            opacity: 0,
            ease: 'back.out(1.7)'
        });
    } else {
        gsap.to('.loading-spinner', {
            duration: 0.3,
            scale: 0.5,
            opacity: 0,
            ease: 'power2.in',
            onComplete: () => {
                overlay.classList.remove('show');
            }
        });
    }
}

// Gestion des erreurs globales
window.addEventListener('error', function(e) {
    console.error('Erreur JavaScript:', e.error);
    showAlert('Une erreur inattendue s\'est produite', 'error');
});

// Gestion de la perte de connexion
window.addEventListener('online', function() {
    showAlert('Connexion rétablie', 'success');
});

window.addEventListener('offline', function() {
    showAlert('Connexion perdue', 'warning');
});