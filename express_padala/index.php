<?php
require_once 'config/database.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BDMPC Express Padala - Document TrackingSystem</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        body {
            background-image: url('assets/images/courier-bg.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            min-height: 100vh;
        } 
        .feature-icon {
            font-size: 2.5rem;
            color: #0d6efd;
            margin-bottom: 1rem;
        }
        /* Original card style */
        .card {
            background: rgba(255, 255, 255, 0.95);
            transition: transform 0.2s;
        }
        .card:hover {
            transform: translateY(-5px);
        }
        /* Glass effect only for header */
        .header-section {
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        .header-section h2 {
            color: #000;
            font-size: 2.5rem;
            text-shadow: 2px 2px 4px rgba(255, 255, 255, 0.5);
            margin-bottom: 10px;
            font-weight: bold;
        }
        .header-section p {
            color: #000;
            font-size: 1.2rem;
            text-shadow: 1px 1px 2px rgba(255, 255, 255, 0.5);
            margin-bottom: 0;
            font-weight: 500;
        }
        /* Make text in cards more visible */
        .card-title {
            color: #000;
            font-weight: bold;
        }
        .card-text {
            color: #000;
        }
        /* Custom button style */
        .btn-primary {
            background: rgba(13, 110, 253, 0.9);
            border: 1px solid rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
        }
        .btn-primary:hover {
            background: rgba(13, 110, 253, 1);
            border: 1px solid rgba(255, 255, 255, 0.5);
        }
    </style>
</head>
<body>
    <?php require_once 'includes/nav.php'; ?>
    
    <div class="container mt-4">
        <div class="row mb-4">
            <div class="col-12 text-center">
                <div class="header-section">
                    <h2><i class="bi bi-send-fill me-2"></i>BDMPC Express Padala</h2>
                    <p class="lead">Document Tracking System</p>
                </div>
            </div>
        </div>
        <div class="row g-4">
            <div class="col-md-6 col-lg-3">
                <div class="card h-100 text-center">
                    <div class="card-body">
                        <div class="feature-icon">
                            <i class="bi bi-file-earmark-plus"></i>
                        </div>
                        <h5 class="card-title">Document Preparation</h5>
                        <p class="card-text">Prepare and upload documents for transit with automatic reference number generation.</p>
                        <a href="pages/document_preparation.php" class="btn btn-primary">
                            <i class="bi bi-plus-circle me-1"></i>New Document
                        </a>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="card h-100 text-center">
                    <div class="card-body">
                        <div class="feature-icon">
                            <i class="bi bi-truck"></i>
                        </div>
                        <h5 class="card-title">Transit to Branches</h5>
                        <p class="card-text">Manage document transit details and assign to specific branches.</p>
                        <a href="pages/transit.php" class="btn btn-primary">
                            <i class="bi bi-arrow-right-circle me-1"></i>Transit Documents
                        </a>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="card h-100 text-center">
                    <div class="card-body">
                        <div class="feature-icon">
                            <i class="bi bi-inbox"></i>
                        </div>
                        <h5 class="card-title">Document Handover</h5>
                        <p class="card-text">Process document receipt and record handover details at branches.</p>
                        <a href="pages/receiver.php" class="btn btn-primary">
                            <i class="bi bi-check-circle me-1"></i>Receive Documents
                        </a>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="card h-100 text-center">
                    <div class="card-body">
                        <div class="feature-icon">
                            <i class="bi bi-clipboard-check"></i>
                        </div>
                        <h5 class="card-title">Delivery Status</h5>
                        <p class="card-text">View comprehensive status of all documents in the system.</p>
                        <a href="pages/status.php" class="btn btn-primary">
                            <i class="bi bi-clipboard-data me-1"></i>View Status
                        </a>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="card h-100 text-center">
                    <div class="card-body">
                        <div class="feature-icon">
                            <i class="bi bi-search"></i>
                        </div>
                        <h5 class="card-title">Track Document</h5>
                        <p class="card-text">Search and track specific documents using reference numbers.</p>
                        <a href="pages/track.php" class="btn btn-primary">
                            <i class="bi bi-search me-1"></i>Track Now
                        </a>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="card h-100 text-center">
                    <div class="card-body">
                        <div class="feature-icon">
                            <i class="bi bi-shield-check"></i>
                        </div>
                        <h5 class="card-title">Audit & Approve</h5>
                        <p class="card-text">Audit and approve delivered documents to ensure compliance and accuracy.</p>
                        <a href="pages/audit.php" class="btn btn-primary">
                            <i class="bi bi-shield me-1"></i>Audit Documents
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 