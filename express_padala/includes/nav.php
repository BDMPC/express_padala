<?php
$current_page = basename($_SERVER['PHP_SELF']);
$base_url = '/express_padala';
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand" href="<?php echo $base_url; ?>/index.php">
            <i class="bi bi-send-fill me-2"></i>BDMPC Express Padala
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'document_preparation.php' ? 'active' : ''; ?>" 
                       href="<?php echo $base_url; ?>/pages/document_preparation.php">
                        <i class="bi bi-file-earmark-plus me-1"></i>Document Preparation
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'transit.php' ? 'active' : ''; ?>" 
                       href="<?php echo $base_url; ?>/pages/transit.php">
                        <i class="bi bi-truck me-1"></i>Transit to Branches
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'receiver.php' ? 'active' : ''; ?>" 
                       href="<?php echo $base_url; ?>/pages/receiver.php">
                        <i class="bi bi-inbox me-1"></i>Document Handover
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'status.php' ? 'active' : ''; ?>" 
                       href="<?php echo $base_url; ?>/pages/status.php">
                        <i class="bi bi-clipboard-check me-1"></i>Delivery Status
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'track.php' ? 'active' : ''; ?>" 
                       href="<?php echo $base_url; ?>/pages/track.php">
                        <i class="bi bi-search me-1"></i>Track Document
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'audit.php' ? 'active' : ''; ?>" 
                       href="<?php echo $base_url; ?>/pages/audit.php">
                        <i class="bi bi-shield-check me-1"></i>Audit & Approve
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav> 