<?php
require_once '../config/database.php';

header('Content-Type: application/json');

try {
    // Start transaction
    $conn->begin_transaction();

    // Insert delivery record
    $stmt = $conn->prepare("INSERT INTO deliveries (document_id, receiver_name, received_datetime, status, review_notes) VALUES (?, ?, ?, 'received', ?)");
    $stmt->bind_param(
        "isss",
        $_POST['document_id'],
        $_POST['receiver_name'],
        $_POST['received_datetime'],
        $_POST['review_notes']
    );
    $stmt->execute();

    // Update transit status
    $stmt = $conn->prepare("UPDATE transits SET status = 'delivered' WHERE id = ?");
    $stmt->bind_param("i", $_POST['transit_id']);
    $stmt->execute();

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Delivery details saved successfully'
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error saving delivery details: ' . $e->getMessage()
    ]);
}

$conn->close(); 