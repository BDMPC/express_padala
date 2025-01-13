<?php
require_once '../config/database.php';

header('Content-Type: application/json');

$store = $_GET['store'] ?? '';
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !$store) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

try {
    $conn->begin_transaction();

    switch ($store) {
        case 'documents':
            // Generate reference number if not provided
            if (!isset($data['reference_number'])) {
                $now = new DateTime();
                $month = $now->format('m');
                $day = $now->format('d');
                $year = $now->format('Y');
                $random = str_pad(mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);
                $data['reference_number'] = "REF-{$month}{$day}{$year}-{$random}";
            }

            // Insert document
            $stmt = $conn->prepare("INSERT INTO documents (reference_number, sender_name, sent_datetime, sync_status) VALUES (?, ?, ?, 'synced')");
            $stmt->bind_param("sss", $data['reference_number'], $data['sender_name'], $data['sent_datetime']);
            $stmt->execute();
            $document_id = $conn->insert_id;

            // Insert document types
            if (isset($data['document_types']) && is_array($data['document_types'])) {
                $stmt = $conn->prepare("INSERT INTO document_types (document_id, type_name) VALUES (?, ?)");
                foreach ($data['document_types'] as $type) {
                    $stmt->bind_param("is", $document_id, $type);
                    $stmt->execute();
                }
            }
            break;

        case 'transits':
            $stmt = $conn->prepare("INSERT INTO transits (document_id, transportation_type, departure_datetime, plate_number, driver_name, driver_contact, branch_id, sync_status) VALUES (?, ?, ?, ?, ?, ?, ?, 'synced')");
            $stmt->bind_param(
                "isssssi",
                $data['document_id'],
                $data['transportation_type'],
                $data['departure_datetime'],
                $data['plate_number'],
                $data['driver_name'],
                $data['driver_contact'] ?? '',
                $data['branch_id']
            );
            $stmt->execute();
            break;

        case 'deliveries':
            // Insert delivery record
            $stmt = $conn->prepare("INSERT INTO deliveries (document_id, receiver_name, received_datetime, status, review_notes, sync_status) VALUES (?, ?, ?, 'received', ?, 'synced')");
            $stmt->bind_param(
                "isss",
                $data['document_id'],
                $data['receiver_name'],
                $data['received_datetime'],
                $data['review_notes'] ?? ''
            );
            $stmt->execute();

            // Update transit status
            if (isset($data['transit_id'])) {
                $stmt = $conn->prepare("UPDATE transits SET status = 'delivered' WHERE id = ?");
                $stmt->bind_param("i", $data['transit_id']);
                $stmt->execute();
            }
            break;

        default:
            throw new Exception('Invalid store type');
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Data synced successfully']);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Sync error: ' . $e->getMessage()]);
}

$conn->close(); 