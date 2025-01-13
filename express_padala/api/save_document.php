<?php
require_once '../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sender_name = $_POST['sender_name'];
    $document_types = isset($_POST['document_types']) ? $_POST['document_types'] : [];
    $reference_number = $_POST['reference_number'];
    
    if (empty($document_types)) {
        echo json_encode(['success' => false, 'message' => 'Please select at least one document type.']);
        exit;
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Increment the sequence number for next use
        $sql = "UPDATE reference_sequence SET sequence_number = sequence_number + 1";
        $conn->query($sql);
        
        // Insert document with the provided reference number
        $stmt = $conn->prepare("INSERT INTO documents (reference_number, sender_name, sent_datetime) VALUES (?, ?, NOW())");
        $stmt->bind_param("ss", $reference_number, $sender_name);
        $stmt->execute();
        
        $document_id = $conn->insert_id;
        
        // Insert document types
        $type_stmt = $conn->prepare("INSERT INTO document_types (document_id, type_name) VALUES (?, ?)");
        foreach ($document_types as $type) {
            $type_stmt->bind_param("is", $document_id, $type);
            $type_stmt->execute();
        }
        
        // Handle uploaded files
        if (isset($_POST['uploaded_files'])) {
            $uploaded_files = json_decode($_POST['uploaded_files'], true);
            if (!empty($uploaded_files)) {
                // Update document_id for uploaded files
                $file_stmt = $conn->prepare("UPDATE document_files SET document_id = ? WHERE id = ?");
                foreach ($uploaded_files as $file_id) {
                    $file_stmt->bind_param("ii", $document_id, $file_id);
                    $file_stmt->execute();
                }
            }
        }
        
        // Commit transaction
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Document saved successfully',
            'reference_number' => $reference_number
        ]);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?> 