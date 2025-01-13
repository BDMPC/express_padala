<?php
require_once '../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $document_id = $_POST['document_id'];
    $approver_name = $_POST['approver_name'];
    $remarks = $_POST['remarks'];
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Check if document exists and is audited
        $check_stmt = $conn->prepare("
            SELECT d.id, a.status as audit_status 
            FROM documents d
            LEFT JOIN audits a ON d.id = a.document_id
            WHERE d.id = ?
        ");
        $check_stmt->bind_param("i", $document_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Document not found");
        }
        
        $doc = $result->fetch_assoc();
        if ($doc['audit_status'] !== 'audited') {
            throw new Exception("Document must be audited before approval");
        }
        
        // Insert approval record
        $stmt = $conn->prepare("
            INSERT INTO approvals (
                document_id, 
                approver_name, 
                approval_datetime, 
                remarks,
                status
            ) VALUES (?, ?, NOW(), ?, 'approved')
        ");
        
        $stmt->bind_param("iss", $document_id, $approver_name, $remarks);
        $stmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Approval saved successfully'
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