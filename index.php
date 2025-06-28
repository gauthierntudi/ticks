<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Système de Gestion de Tickets</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#"><i class="fas fa-ticket-alt me-2"></i>Gestion Tickets</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="#" onclick="showSection('generator')">Générateur</a>
                <a class="nav-link" href="#" onclick="showSection('scanner')">Scanner</a>
                <a class="nav-link" href="#" onclick="showSection('monitoring')">Monitoring</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Section Générateur de Tickets -->
        <div id="generator-section" class="section active">
            <div class="row">
                <div class="col-md-8">
                    <div class="card shadow-sm">
                        <div class="card-header bg-success text-white">
                            <h4><i class="fas fa-plus-circle me-2"></i>Générateur de Tickets</h4>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Type de Ticket</label>
                                    <select class="form-select" id="ticketType">
                                        <option value="jour1">Jour 1</option>
                                        <option value="jour2">Jour 2</option>
                                        <option value="jour3">Jour 3</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Nombre de Tickets</label>
                                    <input type="number" class="form-control" id="ticketCount" value="1" min="1" max="100">
                                </div>
                            </div>
                            <button class="btn btn-success btn-lg" onclick="generateTickets()">
                                <i class="fas fa-magic me-2"></i>Générer Tickets + PDF
                            </button>
                            <div class="mt-2">
                                <small class="text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Le PDF sera automatiquement téléchargé après la génération des tickets
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card shadow-sm">
                        <div class="card-header bg-info text-white">
                            <h5><i class="fas fa-download me-2"></i>Télécharger PDF Existant</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Catégorie</label>
                                <select class="form-select" id="pdfCategory">
                                    <option value="jour1">Jour 1</option>
                                    <option value="jour2">Jour 2</option>
                                    <option value="jour3">Jour 3</option>
                                </select>
                            </div>
                            <button class="btn btn-info" onclick="generatePDF()">
                                <i class="fas fa-file-pdf me-2"></i>Générer PDF
                            </button>
                            <div class="mt-2">
                                <small class="text-muted">
                                    Pour les tickets déjà créés
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mt-4">
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-header">
                            <h5><i class="fas fa-eye me-2"></i>Aperçu des Tickets Générés</h5>
                        </div>
                        <div class="card-body">
                            <div id="ticketPreview" class="ticket-preview-container">
                                <p class="text-muted text-center">Aucun ticket généré</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section Scanner QR -->
        <div id="scanner-section" class="section">
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card shadow-sm">
                        <div class="card-header bg-warning text-dark">
                            <h4><i class="fas fa-qrcode me-2"></i>Scanner QR Code</h4>
                        </div>
                        <div class="card-body text-center">
                            <div id="qr-reader" class="qr-reader-container mb-3"></div>
                            <div class="mb-3">
                                <button class="btn btn-warning" id="start-scan">
                                    <i class="fas fa-camera me-2"></i>Démarrer Scanner
                                </button>
                                <button class="btn btn-secondary" id="stop-scan" style="display: none;">
                                    <i class="fas fa-stop me-2"></i>Arrêter Scanner
                                </button>
                            </div>
                            <div id="scan-result" class="scan-result"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section Monitoring -->
        <div id="monitoring-section" class="section">
            <div class="card shadow-sm">
                <div class="card-header bg-dark text-white">
                    <h4><i class="fas fa-chart-bar me-2"></i>Monitoring des Tickets</h4>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="stat-card bg-primary">
                                <div class="stat-number" id="total-tickets">0</div>
                                <div class="stat-label">Total Tickets</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card bg-success">
                                <div class="stat-number" id="scanned-tickets">0</div>
                                <div class="stat-label">Tickets Scannés</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card bg-warning">
                                <div class="stat-number" id="pending-tickets">0</div>
                                <div class="stat-label">En Attente</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card bg-info">
                                <div class="stat-number" id="scan-rate">0%</div>
                                <div class="stat-label">Taux de Scan</div>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead class="table-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>QR Code</th>
                                    <th>Type</th>
                                    <th>Statut</th>
                                    <th>Date Création</th>
                                    <th>Date Scan</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="tickets-table">
                                <!-- Les données seront chargées via AJAX -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Résultat de Scan -->
    <div class="modal fade" id="scanResultModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Résultat du Scan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="scanModalBody">
                    <!-- Contenu dynamique -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div id="loading-overlay" class="loading-overlay">
        <div class="loading-spinner">
            <i class="fas fa-spinner fa-spin fa-3x"></i>
            <p class="mt-3">Génération en cours...</p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/ScrollTrigger.min.js"></script>
    <script src="https://unpkg.com/html5-qrcode/minified/html5-qrcode.min.js"></script>
    <script src="js/app.js"></script>
</body>
</html>