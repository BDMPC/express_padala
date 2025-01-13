<?php
require_once '../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $document_id = $_POST['document_id'];
    $auditor_name = $_POST['auditor_name'];
    $remarks = $_POST['remarks'];
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Check if document exists and is delivered
        $check_stmt = $conn->prepare("
            SELECT d.id, del.status as delivery_status 
            FROM documents d
            LEFT JOIN deliveries del ON d.id = del.document_id
            WHERE d.id = ?
        ");
        $check_stmt->bind_param("i", $document_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Document not found");
        }
        
        $doc = $result->fetch_assoc();
        if ($doc['delivery_status'] !== 'received') {
            throw new Exception("Document must be delivered before auditing");
        }
        
        // Insert audit record
        $stmt = $conn->prepare("
            INSERT INTO audits (
                document_id, 
                auditor_name, 
                audit_datetime, 
                remarks,
                status
            ) VALUES (?, ?, NOW(), ?, 'audited')
        ");
        
        $stmt->bind_param("iss", $document_id, $auditor_name, $remarks);
        $stmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Audit saved successfully'
        ]);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
}
?> 