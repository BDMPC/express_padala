<?php
require_once '../config/database.php';

// Get documents that need auditing (delivered documents)
$sql = "SELECT 
            d.id,
            d.reference_number,
            d.sender_name,
            d.sent_datetime,
            GROUP_CONCAT(DISTINCT dt.type_name) as document_types,
            t.transportation_type,
            t.driver_name,
            t.sprinter_rider_name,
            b.branch_name,
            del.receiver_name,
            del.received_datetime,
            a.status as audit_status,
            a.remarks as audit_remarks,
            a.auditor_name,
            a.audit_datetime,
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
        WHERE del.status = 'received'
        GROUP BY d.id
        ORDER BY d.sent_datetime DESC";

$result = $conn->query($sql);

if (!$result) {
    die("Error fetching documents: " . $conn->error);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit and Approve - Express Padala</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        .table th {
            background-color: #f8f9fa;
        }
        .badge {
            font-size: 0.9em;
        }
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
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-shield-check me-2"></i>Audit and Approve Documents
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th><i class="bi bi-hash me-1"></i>Reference</th>
                                        <th><i class="bi bi-person me-1"></i>Sender</th>
                                        <th><i class="bi bi-files me-1"></i>Document Types</th>
                                        <th><i class="bi bi-building me-1"></i>Branch</th>
                                        <th><i class="bi bi-person-check me-1"></i>Receiver</th>
                                        <th><i class="bi bi-shield me-1"></i>Audit Status</th>
                                        <th><i class="bi bi-check-circle me-1"></i>Approval Status</th>
                                        <th><i class="bi bi-gear me-1"></i>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <a href="track.php?ref=<?php echo urlencode($row['reference_number']); ?>" class="text-decoration-none">
                                                <?php echo htmlspecialchars($row['reference_number']); ?>
                                            </a>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['sender_name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['document_types']); ?></td>
                                        <td><?php echo htmlspecialchars($row['branch_name']); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($row['receiver_name']); ?>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo date('M d, Y h:i A', strtotime($row['received_datetime'])); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <?php if ($row['audit_status'] == 'audited'): ?>
                                                <span class="badge bg-success">Audited</span>
                                                <br>
                                                <small class="text-muted">
                                                    by <?php echo htmlspecialchars($row['auditor_name']); ?>
                                                    <br>
                                                    <?php echo date('M d, Y h:i A', strtotime($row['audit_datetime'])); ?>
                                                </small>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark">Pending Audit</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($row['approval_status'] == 'approved'): ?>
                                                <span class="badge bg-success">Approved</span>
                                                <br>
                                                <small class="text-muted">
                                                    by <?php echo htmlspecialchars($row['approver_name']); ?>
                                                    <br>
                                                    <?php echo date('M d, Y h:i A', strtotime($row['approval_datetime'])); ?>
                                                </small>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark">Pending Approval</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <?php if ($row['audit_status'] != 'audited'): ?>
                                                    <button type="button" class="btn btn-primary btn-sm audit-btn"
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#auditModal"
                                                            data-id="<?php echo $row['id']; ?>"
                                                            data-reference="<?php echo htmlspecialchars($row['reference_number']); ?>">
                                                        <i class="bi bi-shield"></i> Audit
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-info btn-sm"
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#viewRemarksModal"
                                                            data-remarks="<?php echo htmlspecialchars($row['audit_remarks']); ?>"
                                                            data-title="Audit Remarks"
                                                            data-type="audit">
                                                        <i class="bi bi-eye"></i> View Audit
                                                    </button>
                                                <?php endif; ?>

                                                <?php if ($row['approval_status'] != 'approved' && $row['audit_status'] == 'audited'): ?>
                                                    <button type="button" class="btn btn-success btn-sm approve-btn"
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#approveModal"
                                                            data-id="<?php echo $row['id']; ?>"
                                                            data-reference="<?php echo htmlspecialchars($row['reference_number']); ?>">
                                                        <i class="bi bi-check-circle"></i> Approve
                                                    </button>
                                                <?php elseif ($row['approval_status'] == 'approved'): ?>
                                                    <button type="button" class="btn btn-info btn-sm"
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#viewRemarksModal"
                                                            data-remarks="<?php echo htmlspecialchars($row['approval_remarks']); ?>"
                                                            data-title="Approval Remarks"
                                                            data-type="approve">
                                                        <i class="bi bi-eye"></i> View Approval
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Audit Modal -->
    <div class="modal fade" id="auditModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Audit Document</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="auditForm" action="../api/save_audit.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="document_id" id="audit_document_id">
                        <div class="mb-3">
                            <label class="form-label">Reference Number:</label>
                            <div class="form-control-plaintext" id="audit_reference_display"></div>
                        </div>
                        <div class="mb-3">
                            <label for="auditor_name" class="form-label">Auditor Name</label>
                            <input type="text" class="form-control" id="auditor_name" name="auditor_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="audit_remarks" class="form-label">Audit Remarks</label>
                            <textarea class="form-control" id="audit_remarks" name="remarks" rows="3" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-shield-check me-1"></i>Submit Audit
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Approve Modal -->
    <div class="modal fade" id="approveModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Approve Document</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="approveForm" action="../api/save_approval.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="document_id" id="approve_document_id">
                        <div class="mb-3">
                            <label class="form-label">Reference Number:</label>
                            <div class="form-control-plaintext" id="approve_reference_display"></div>
                        </div>
                        <div class="mb-3">
                            <label for="approver_name" class="form-label">Approver Name</label>
                            <input type="text" class="form-control" id="approver_name" name="approver_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="approve_remarks" class="form-label">Approval Remarks</label>
                            <textarea class="form-control" id="approve_remarks" name="remarks" rows="3" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check-circle me-1"></i>Submit Approval
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Remarks Modal -->
    <div class="modal fade" id="viewRemarksModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="remarksTitle">Remarks</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p id="remarksContent"></p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Handle audit button clicks
            document.querySelectorAll('.audit-btn').forEach(button => {
                button.addEventListener('click', function() {
                    document.getElementById('audit_document_id').value = this.dataset.id;
                    document.getElementById('audit_reference_display').textContent = this.dataset.reference;
                });
            });

            // Handle approve button clicks
            document.querySelectorAll('.approve-btn').forEach(button => {
                button.addEventListener('click', function() {
                    document.getElementById('approve_document_id').value = this.dataset.id;
                    document.getElementById('approve_reference_display').textContent = this.dataset.reference;
                });
            });

            // Handle view remarks button clicks
            document.querySelectorAll('[data-bs-target="#viewRemarksModal"]').forEach(button => {
                button.addEventListener('click', function() {
                    document.getElementById('remarksTitle').textContent = this.dataset.title;
                    document.getElementById('remarksContent').textContent = this.dataset.remarks;
                });
            });

            // Handle audit form submission
            document.getElementById('auditForm').addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                
                fetch(this.action, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        alert('Audit saved successfully');
                        window.location.reload();
                    } else {
                        alert('Error: ' + result.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while saving the audit');
                });
            });

            // Handle approve form submission
            document.getElementById('approveForm').addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                
                fetch(this.action, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        alert('Approval saved successfully');
                        window.location.reload();
                    } else {
                        alert('Error: ' + result.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while saving the approval');
                });
            });
        });
    </script>
</body>
</html> 