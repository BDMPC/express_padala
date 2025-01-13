<?php
require_once '../config/database.php';

header('Content-Type: application/json');

try {
    // Start transaction
    $conn->begin_transaction();

    // Validate branch exists
    $stmt = $conn->prepare("SELECT id FROM branches WHERE id = ?");
    $branch_id = $_POST['branch_id'];
    $stmt->bind_param("i", $branch_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Invalid branch selected");
    }

    // Prepare transit insert statement
    $stmt = $conn->prepare("INSERT INTO transits (document_id, transportation_type, plate_number, driver_name, driver_contact, sprinter_rider_name, branch_id, departure_datetime, estimated_arrival) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

    // Store form values
    $transport_type = $_POST['transportation_type'];
    $departure_time = $_POST['departure_datetime'];
    $plate_number = $_POST['plate_number'];
    $driver_name = $_POST['driver_name'];
    $driver_contact = $_POST['driver_contact'] ?? '';
    $sprinter_rider_name = $_POST['sprinter_rider_name'];

    // Insert transit record for each selected document
    foreach ($_POST['document_ids'] as $document_id) {
        // Check if document is already in transit
        $check = $conn->prepare("SELECT id FROM transits WHERE document_id = ?");
        $check->bind_param("i", $document_id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            throw new Exception("One or more documents are already in transit");
        }

        $stmt->bind_param(
            "issssssss",
            $document_id,
            $transport_type,
            $plate_number,
            $driver_name,
            $driver_contact,
            $sprinter_rider_name,
            $branch_id,
            $departure_time,
            $_POST['estimated_arrival']
        );
        $stmt->execute();
    }

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Transit details saved successfully'
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error saving transit details: ' . $e->getMessage()
    ]);
}

$conn->close(); 