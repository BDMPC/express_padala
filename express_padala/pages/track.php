<?php
require_once '../config/database.php';

$reference = $_GET['ref'] ?? '';
$document = null;
$timeline = [];

if ($reference) {
    // Get document details
    $stmt = $conn->prepare("
        SELECT 
            d.*,
            GROUP_CONCAT(DISTINCT dt.type_name) as document_types,
            GROUP_CONCAT(DISTINCT dt.reference_number) as type_references,
            t.id as transit_id,
            t.transportation_type,
            t.departure_datetime,
            t.estimated_arrival,
            t.plate_number,
            t.driver_name,
            t.driver_contact,
            t.sprinter_rider_name,
            b.branch_name,
            t.status as transit_status,
            del.receiver_name,
            del.received_datetime,
            del.status as delivery_status,
            COALESCE(del.review_notes, '') as review_notes,
            a.status as audit_status,
            a.auditor_name,
            a.audit_datetime,
            a.remarks as audit_remarks,
            ap.status as approval_status,
            ap.approver_name,
            ap.approval_datetime,
            ap.remarks as approval_remarks
        FROM documents d
        LEFT JOIN document_types dt ON d.id = dt.document_id
        LEFT JOIN transits t ON d.id = t.document_id
        LEFT JOIN branches b ON t.branch_id = b.id
        LEFT JOIN deliveries del ON d.id = del.document_id
        LEFT JOIN audits a ON d.id = a.document_id
        LEFT JOIN approvals ap ON d.id = ap.document_id
        WHERE d.reference_number = ?
        GROUP BY d.id
    ");
    
    if (!$stmt) {
        die("Error preparing statement: " . $conn->error);
    }
    
    $stmt->bind_param("s", $reference);
    $stmt->execute();
    $result = $stmt->get_result();
    $document = $result->fetch_assoc();

    if ($document) {
        // Build timeline
        $timeline[] = [
            'date' => $document['sent_datetime'],
            'status' => 'Document Registered',
            'details' => "Sender: {$document['sender_name']}<br>Document Types: {$document['document_types']}"
        ];

        if ($document['transit_id']) {
            $timeline_transit_text = "Via: {$document['transportation_type']}<br>" .
                             "Driver: {$document['driver_name']}";
            
            if ($document['driver_contact']) {
                $timeline_transit_text .= "<br>Contact: {$document['driver_contact']}";
            }
            
            $timeline_transit_text .= "<br>BDMPC Sprinter/Rider: {$document['sprinter_rider_name']}" .
                              "<br>To: {$document['branch_name']}" .
                              "<br>Plate #: {$document['plate_number']}" .
                              "<br>Est. Arrival: " . date('M d, Y h:i A', strtotime($document['estimated_arrival']));

            $timeline[] = [
                'date' => $document['departure_datetime'],
                'status' => 'In Transit',
                'details' => $timeline_transit_text
            ];
        }

        if ($document['delivery_status'] == 'received') {
            $details = "Receiver: {$document['receiver_name']}";
            if (!empty($document['review_notes'])) {
                $details .= "<br>Notes: {$document['review_notes']}";
            }
            
            $timeline[] = [
                'date' => $document['received_datetime'],
                'status' => 'Delivered',
                'details' => $details
            ];
        }

        if ($document['audit_status'] == 'audited') {
            $details = "Auditor: {$document['auditor_name']}";
            if (!empty($document['audit_remarks'])) {
                $details .= "<br>Remarks: {$document['audit_remarks']}";
            }
            
            $timeline[] = [
                'date' => $document['audit_datetime'],
                'status' => 'Audited',
                'details' => $details
            ];
        }

        if ($document['approval_status'] == 'approved') {
            $details = "Approver: {$document['approver_name']}";
            if (!empty($document['approval_remarks'])) {
                $details .= "<br>Remarks: {$document['approval_remarks']}";
            }
            
            $timeline[] = [
                'date' => $document['approval_datetime'],
                'status' => 'Approved',
                'details' => $details
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Document - Express Padala</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        .timeline {
            position: relative;
            padding: 20px 0;
        }
        .timeline-item {
            padding: 20px;
            margin-bottom: 20px;
            border-left: 3px solid #0d6efd;
            position: relative;
            background: #f8f9fa;
            border-radius: 0 5px 5px 0;
        }
        .timeline-date {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 5px;
        }
        .timeline-item:before {
            content: '';
            position: absolute;
            left: -9px;
            top: 24px;
            width: 15px;
            height: 15px;
            border-radius: 50%;
            background: #0d6efd;
        }
    </style>
</head>
<body>
    <?php require_once '../includes/nav.php'; ?>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-search me-2"></i>Track Document
                        </h5>
                    </div>
                    <div class="card-body">
                        <form action="" method="GET" class="mb-4">
                            <div class="row g-3 align-items-center">
                                <div class="col-auto">
                                    <label for="ref" class="col-form-label">Reference Number:</label>
                                </div>
                                <div class="col-md-4">
                                    <input type="text" id="ref" name="ref" class="form-control" 
                                           value="<?php echo htmlspecialchars($reference); ?>" required>
                                </div>
                                <div class="col-auto">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-search me-1"></i>Track
                                    </button>
                                </div>
                            </div>
                        </form>

                        <?php if ($reference && !$document): ?>
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle me-2"></i>No document found with reference number: <?php echo htmlspecialchars($reference); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($document): ?>
                            <div class="tracking-details">
                                <div class="mb-4">
                                    <p class="mb-1"><strong>Reference:</strong> <?php echo htmlspecialchars($document['reference_number']); ?></p>
                                    <p class="mb-1"><strong>Sender:</strong> <?php echo htmlspecialchars($document['sender_name']); ?></p>
                                    <p class="mb-1"><strong>Document Types:</strong> <?php echo htmlspecialchars($document['document_types']); ?></p>
                                    <p class="mb-0">
                                        <strong>Status:</strong>
                                        <?php
                                        if ($document['approval_status'] == 'approved') {
                                            echo '<span class="badge bg-success">Approved</span>';
                                        } elseif ($document['audit_status'] == 'audited') {
                                            echo '<span class="badge bg-info">Audited</span>';
                                        } elseif ($document['delivery_status'] == 'received') {
                                            echo '<span class="badge bg-primary">Delivered</span>';
                                        } elseif ($document['transit_status'] == 'in_transit') {
                                            echo '<span class="badge bg-warning">In Transit</span>';
                                        } else {
                                            echo '<span class="badge bg-secondary">Processing</span>';
                                        }
                                        ?>
                                    </p>

                                    <?php
                                    // Get attached files
                                    $files_stmt = $conn->prepare("
                                        SELECT df.*, d.reference_number
                                        FROM document_files df
                                        JOIN documents d ON df.document_id = d.id
                                        WHERE df.document_id = ? AND df.status = 'active'
                                    ");
                                    $files_stmt->bind_param("i", $document['id']);
                                    $files_stmt->execute();
                                    $files_result = $files_stmt->get_result();
                                    
                                    if ($files_result->num_rows > 0): ?>
                                        <div class="mt-3">
                                            <strong>Attached Files:</strong>
                                            <div class="list-group mt-2">
                                                <?php while ($file = $files_result->fetch_assoc()): 
                                                    $file_icon = match(strtolower($file['file_type'])) {
                                                        'pdf' => 'file-earmark-pdf',
                                                        'doc', 'docx' => 'file-earmark-word',
                                                        'xls', 'xlsx' => 'file-earmark-excel',
                                                        'txt' => 'file-earmark-text',
                                                        default => 'file-earmark'
                                                    };
                                                    $file_size = number_format($file['file_size'] / 1024, 2) . ' KB';
                                                ?>
                                                    <div class="list-group-item">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <div>
                                                                <i class="bi bi-<?php echo $file_icon; ?> me-2"></i>
                                                                <?php echo htmlspecialchars($file['original_name']); ?>
                                                                <br>
                                                                <small class="text-muted">
                                                                    Type: <?php echo strtoupper($file['file_type']); ?> | 
                                                                    Size: <?php echo $file_size; ?>
                                                                </small>
                                                            </div>
                                                            <div class="btn-group">
                                                                <button type="button" class="btn btn-sm btn-outline-primary preview-btn" 
                                                                        data-file-id="<?php echo $file['id']; ?>"
                                                                        data-file-type="<?php echo $file['file_type']; ?>">
                                                                    <i class="bi bi-eye"></i> Preview
                                                                </button>
                                                                <a href="../api/handle_file.php?action=download&id=<?php echo $file['id']; ?>" 
                                                                   class="btn btn-sm btn-outline-success">
                                                                    <i class="bi bi-download"></i> Download
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endwhile; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <h6 class="mb-3">Tracking Timeline</h6>
                                <div class="timeline">
                                    <?php foreach ($timeline as $event): ?>
                                    <div class="timeline-item">
                                        <div class="timeline-date">
                                            <?php echo date('M d, Y H:i', strtotime($event['date'])); ?>
                                        </div>
                                        <h6 class="mb-2"><?php echo htmlspecialchars($event['status']); ?></h6>
                                        <p class="mb-0"><?php echo $event['details']; ?></p>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Preview Modal -->
    <div class="modal fade" id="previewModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">File Preview</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center" id="previewLoading">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                    <div id="previewContent"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const previewModal = new bootstrap.Modal(document.getElementById('previewModal'));
            const previewContent = document.getElementById('previewContent');
            const previewLoading = document.getElementById('previewLoading');

            // Handle preview button clicks
            document.querySelectorAll('.preview-btn').forEach(button => {
                button.addEventListener('click', async function() {
                    const fileId = this.dataset.fileId;
                    const fileType = this.dataset.fileType.toLowerCase();

                    // Show modal with loading spinner
                    previewContent.innerHTML = '';
                    previewLoading.style.display = 'block';
                    previewModal.show();

                    try {
                        // Get file details from API
                        const response = await fetch(`../api/handle_file.php?action=preview&id=${fileId}`);
                        const data = await response.json();

                        if (data.success) {
                            previewLoading.style.display = 'none';

                            // Handle different file types
                            if (fileType === 'pdf') {
                                previewContent.innerHTML = `
                                    <iframe src="${data.viewer_url}" 
                                            style="width: 100%; height: 600px;" frameborder="0"></iframe>
                                `;
                            } else if (['doc', 'docx', 'xls', 'xlsx'].includes(fileType)) {
                                previewContent.innerHTML = `
                                    <div class="text-center">
                                        <p>This file type requires Microsoft Office Online Viewer.</p>
                                        <a href="${data.viewer_url}" class="btn btn-primary" target="_blank">
                                            Open in Office Online Viewer
                                        </a>
                                    </div>
                                `;
                            } else if (fileType === 'txt') {
                                previewContent.innerHTML = `
                                    <iframe src="${data.viewer_url}" 
                                            style="width: 100%; height: 400px;" frameborder="0"></iframe>
                                `;
                            } else {
                                previewContent.innerHTML = `
                                    <div class="text-center">
                                        <p>Preview not available for this file type.</p>
                                        <a href="../api/handle_file.php?action=download&id=${fileId}" 
                                           class="btn btn-primary">Download File</a>
                                    </div>
                                `;
                            }
                        } else {
                            throw new Error(data.message);
                        }
                    } catch (error) {
                        previewLoading.style.display = 'none';
                        previewContent.innerHTML = `
                            <div class="alert alert-danger">
                                Error loading preview: ${error.message}
                            </div>
                        `;
                    }
                });
            });
        });
    </script>
</body>
</html> 