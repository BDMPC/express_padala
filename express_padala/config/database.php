<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'express_padala');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database if not exists
$sql = "CREATE DATABASE IF NOT EXISTS " . DB_NAME;
if ($conn->query($sql) === TRUE) {
    $conn->select_db(DB_NAME);
    
    // Create uploads directory if it doesn't exist
    $uploads_dir = __DIR__ . '/../uploads';
    if (!file_exists($uploads_dir)) {
        mkdir($uploads_dir, 0777, true);
        // Create .htaccess to prevent direct access
        file_put_contents($uploads_dir . '/.htaccess', "Deny from all");
    }
    
    // Create necessary tables
    $tables = [
        "CREATE TABLE IF NOT EXISTS documents (
            id INT AUTO_INCREMENT PRIMARY KEY,
            reference_number VARCHAR(20) UNIQUE,
            sender_name VARCHAR(100),
            sent_datetime DATETIME,
            sync_status ENUM('pending', 'synced') DEFAULT 'synced',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",
        
        "CREATE TABLE IF NOT EXISTS document_types (
            id INT AUTO_INCREMENT PRIMARY KEY,
            document_id INT,
            type_name VARCHAR(50),
            reference_number VARCHAR(50),
            FOREIGN KEY (document_id) REFERENCES documents(id)
        )",
        
        "CREATE TABLE IF NOT EXISTS document_files (
            id INT AUTO_INCREMENT PRIMARY KEY,
            document_id INT,
            file_name VARCHAR(255),
            original_name VARCHAR(255),
            file_path VARCHAR(255),
            file_type VARCHAR(50),
            file_size INT,
            mime_type VARCHAR(100),
            status ENUM('active', 'deleted') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (document_id) REFERENCES documents(id)
        )",
        
        "CREATE TABLE IF NOT EXISTS branches (
            id INT AUTO_INCREMENT PRIMARY KEY,
            branch_name VARCHAR(100) UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        
        "CREATE TABLE IF NOT EXISTS transits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            document_id INT,
            transportation_type VARCHAR(50),
            departure_datetime DATETIME,
            plate_number VARCHAR(20),
            driver_name VARCHAR(100),
            driver_contact VARCHAR(20),
            branch_id INT,
            status ENUM('in_transit', 'delivered') DEFAULT 'in_transit',
            sync_status ENUM('pending', 'synced') DEFAULT 'synced',
            FOREIGN KEY (document_id) REFERENCES documents(id),
            FOREIGN KEY (branch_id) REFERENCES branches(id)
        )",
        
        "CREATE TABLE IF NOT EXISTS deliveries (
            id INT AUTO_INCREMENT PRIMARY KEY,
            document_id INT,
            receiver_name VARCHAR(100),
            received_datetime DATETIME,
            review_notes TEXT,
            status ENUM('received', 'pending') DEFAULT 'pending',
            sync_status ENUM('pending', 'synced') DEFAULT 'synced',
            FOREIGN KEY (document_id) REFERENCES documents(id)
        )",
        
        "CREATE TABLE IF NOT EXISTS audits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            document_id INT,
            auditor_name VARCHAR(100),
            audit_datetime DATETIME,
            remarks TEXT,
            status ENUM('pending', 'audited') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (document_id) REFERENCES documents(id)
        )",
        
        "CREATE TABLE IF NOT EXISTS approvals (
            id INT AUTO_INCREMENT PRIMARY KEY,
            document_id INT,
            approver_name VARCHAR(100),
            approval_datetime DATETIME,
            remarks TEXT,
            status ENUM('pending', 'approved') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (document_id) REFERENCES documents(id)
        )"
    ];
    
    foreach ($tables as $table) {
        $conn->query($table);
    }

    // Add driver_contact column if it doesn't exist
    $check_column = $conn->query("SHOW COLUMNS FROM transits LIKE 'driver_contact'");
    if ($check_column->num_rows == 0) {
        $conn->query("ALTER TABLE transits ADD COLUMN driver_contact VARCHAR(20) AFTER driver_name");
    }

    // Add branch_id column if it doesn't exist
    $check_column = $conn->query("SHOW COLUMNS FROM transits LIKE 'branch_id'");
    if ($check_column->num_rows == 0) {
        $conn->query("ALTER TABLE transits ADD COLUMN branch_id INT AFTER driver_contact");
        $conn->query("ALTER TABLE transits ADD FOREIGN KEY (branch_id) REFERENCES branches(id)");
    }

    // Add estimated_arrival column if it doesn't exist
    $check_column = $conn->query("SHOW COLUMNS FROM transits LIKE 'estimated_arrival'");
    if ($check_column->num_rows == 0) {
        $conn->query("ALTER TABLE transits ADD COLUMN estimated_arrival DATETIME AFTER departure_datetime");
    }

    // Add sprinter_rider_name column if it doesn't exist
    $check_column = $conn->query("SHOW COLUMNS FROM transits LIKE 'sprinter_rider_name'");
    if ($check_column->num_rows == 0) {
        $conn->query("ALTER TABLE transits ADD COLUMN sprinter_rider_name VARCHAR(100) AFTER driver_contact");
    }

    // Insert default branches if they don't exist
    $default_branches = [
        'MAIN BRANCH',
        'PANGLAO BRANCH',
        'JAGNA BRANCH',
        'CARMEN BRANCH',
        'TALIBON BRANCH',
        'UBAY BRANCH',
        'TUBIGON BRANCH'
    ];

    $stmt = $conn->prepare("INSERT IGNORE INTO branches (branch_name) VALUES (?)");
    foreach ($default_branches as $branch) {
        $stmt->bind_param("s", $branch);
        $stmt->execute();
    }

    // Create sequence table if it doesn't exist
    $sql = "CREATE TABLE IF NOT EXISTS reference_sequence (
        id INT PRIMARY KEY AUTO_INCREMENT,
        sequence_number INT NOT NULL DEFAULT 1
    )";

    if (!$conn->query($sql)) {
        die("Error creating sequence table: " . $conn->error);
    }

    // Insert initial sequence if table is empty
    $sql = "INSERT INTO reference_sequence (sequence_number) 
            SELECT 1 
            WHERE NOT EXISTS (SELECT 1 FROM reference_sequence)";

    $conn->query($sql);
} else {
    die("Error creating database: " . $conn->error);
}
?> 