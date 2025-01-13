<?php
require_once '../config/database.php';

// Get documents in transit that haven't been received
$sql = "SELECT t.id as transit_id, d.id as document_id, d.reference_number, d.sender_name, 
        GROUP_CONCAT(DISTINCT dt.type_name) as document_types, 
        GROUP_CONCAT(DISTINCT df.id, ':', df.original_name, ':', df.file_type SEPARATOR '|') as files,
        t.transportation_type, t.driver_name, t.driver_contact, 
        b.branch_name as destination, t.departure_datetime
        FROM transits t
        JOIN documents d ON t.document_id = d.id
        LEFT JOIN document_types dt ON d.id = dt.document_id
        LEFT JOIN document_files df ON d.id = df.document_id AND df.status = 'active'
        LEFT JOIN branches b ON t.branch_id = b.id
        LEFT JOIN deliveries del ON d.id = del.document_id
        WHERE t.status = 'in_transit' AND del.id IS NULL
        GROUP BY t.id";
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
    <title>Document Handover - Express Padala</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        .form-submitting {
            opacity: 0.6;
            pointer-events: none;
        }
        .spinner-border {
            width: 1rem;
            height: 1rem;
            margin-right: 0.5rem;
            display: none;
        }
    </style>
</head>
<body>
    <?php require_once '../includes/nav.php'; ?>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-inbox me-2"></i>Document Handover
                        </h5>
                    </div>
                    <div class="card-body">
                        <!-- Success Message Container -->
                        <div id="success-message" class="alert alert-success" style="display: none;">
                            Document received successfully!
                            <button type="button" class="btn-close float-end" aria-label="Close"></button>
                        </div>

                        <form id="receiverForm" class="offline-form" data-store="deliveries" action="../api/save_delivery.php" method="POST">
                            <div class="mb-3">
                                <label class="form-label">Select Document for Handover</label>
                                <select class="form-select" name="document_id" required>
                                    <option value="">Select Document</option>
                                    <?php while($row = $result->fetch_assoc()): ?>
                                    <?php
                                    $files_html = '';
                                    if (!empty($row['files'])) {
                                        $files = array_map(function($file) {
                                            $parts = explode(':', $file);
                                            return [
                                                'id' => $parts[0],
                                                'name' => $parts[1],
                                                'type' => $parts[2]
                                            ];
                                        }, explode('|', $row['files']));
                                        
                                        $files_html = '<div class="mt-1"><small class="text-muted">Attached Files:</small><br>';
                                        foreach ($files as $file) {
                                            $files_html .= sprintf(
                                                '<div class="mt-1">
                                                    <small>%s</small>
                                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="previewFile(%d)">
                                                        <i class="bi bi-eye"></i> Preview
                                                    </button>
                                                    <a href="../api/handle_file.php?action=download&id=%d" class="btn btn-sm btn-outline-secondary">
                                                        <i class="bi bi-download"></i> Download
                                                    </a>
                                                </div>',
                                                htmlspecialchars($file['name']),
                                                $file['id'],
                                                $file['id']
                                            );
                                        }
                                        $files_html .= '</div>';
                                    }
                                    ?>
                                    <option value="<?php echo $row['document_id']; ?>" 
                                            data-transit-id="<?php echo $row['transit_id']; ?>"
                                            data-files='<?php echo !empty($files) ? json_encode($files) : '[]'; ?>'>
                                        REF: <?php echo htmlspecialchars($row['reference_number']); ?> - 
                                        From: <?php echo htmlspecialchars($row['sender_name']); ?> - 
                                        Types: <?php echo htmlspecialchars($row['document_types']); ?> - 
                                        Via: <?php echo htmlspecialchars($row['transportation_type']); ?> - 
                                        To: <?php echo htmlspecialchars($row['destination']); ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div id="attachedFiles" class="mb-3" style="display: none;">
                                <label class="form-label">Attached Files</label>
                                <div id="filesList" class="list-group"></div>
                            </div>

                            <input type="hidden" name="transit_id" id="transit_id">

                            <div class="mb-3">
                                <label class="form-label">Receiver's Name</label>
                                <input type="text" class="form-control" name="receiver_name" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Received Date & Time</label>
                                <input type="datetime-local" class="form-control" name="received_datetime" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Document Review</label>
                                <textarea class="form-control" name="review_notes" rows="3" placeholder="Enter any notes about the document condition"></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary" id="submitBtn">
                                <span class="spinner-border" role="status" aria-hidden="true"></span>
                                Confirm Receipt
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('receiverForm');
            const successMessage = document.getElementById('success-message');
            const submitBtn = document.getElementById('submitBtn');
            const spinner = submitBtn.querySelector('.spinner-border');
            let isSubmitting = false;

            // Close success message
            document.querySelector('.btn-close')?.addEventListener('click', function() {
                successMessage.style.display = 'none';
            });

            // Update transit_id when document is selected
            document.querySelector('select[name="document_id"]').addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                document.getElementById('transit_id').value = selectedOption.dataset.transitId;
            });

            // Set min datetime for received_datetime to current time
            const receivedDatetime = document.querySelector('input[name="received_datetime"]');
            const now = new Date();
            now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
            receivedDatetime.min = now.toISOString().slice(0, 16);
            receivedDatetime.value = now.toISOString().slice(0, 16);

            // Handle form submission
            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                if (isSubmitting) {
                    return;
                }

                // Disable form and show spinner
                isSubmitting = true;
                form.classList.add('form-submitting');
                spinner.style.display = 'inline-block';
                submitBtn.disabled = true;
                
                const formData = new FormData(this);
                
                try {
                    const response = await fetch(this.action, {
                        method: 'POST',
                        body: formData
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        // Show success message
                        successMessage.style.display = 'block';
                        
                        // Reset form
                        form.reset();
                        
                        // Redirect to home page after 1.5 seconds
                        setTimeout(() => {
                            window.location.href = '../index.php';
                        }, 1500);
                    } else {
                        alert('Error: ' + result.message);
                        // Re-enable form on error
                        form.classList.remove('form-submitting');
                        spinner.style.display = 'none';
                        submitBtn.disabled = false;
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('An error occurred while saving the receipt.');
                    // Re-enable form on error
                    form.classList.remove('form-submitting');
                    spinner.style.display = 'none';
                    submitBtn.disabled = false;
                }

                isSubmitting = false;
            });

            const documentSelect = document.querySelector('select[name="document_id"]');
            const attachedFilesDiv = document.getElementById('attachedFiles');
            const filesList = document.getElementById('filesList');

            function getFileIcon(type) {
                switch(type.toLowerCase()) {
                    case 'pdf': return 'file-earmark-pdf';
                    case 'doc':
                    case 'docx': return 'file-earmark-word';
                    case 'xls':
                    case 'xlsx': return 'file-earmark-excel';
                    case 'txt': return 'file-earmark-text';
                    default: return 'file-earmark';
                }
            }

            function updateFilesList(selectedOption) {
                if (!selectedOption || !selectedOption.value === '') {
                    attachedFilesDiv.style.display = 'none';
                    return;
                }

                const files = JSON.parse(selectedOption.dataset.files || '[]');
                
                if (files.length === 0) {
                    attachedFilesDiv.style.display = 'none';
                    return;
                }

                filesList.innerHTML = files.map(file => `
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <i class="bi bi-${getFileIcon(file.type)}"></i> 
                                ${file.name}
                            </div>
                            <div class="btn-group">
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="previewFile(${file.id})">
                                    <i class="bi bi-eye"></i> Preview
                                </button>
                                <a href="../api/handle_file.php?action=download&id=${file.id}" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-download"></i> Download
                                </a>
                            </div>
                        </div>
                    </div>
                `).join('');

                attachedFilesDiv.style.display = 'block';
            }

            documentSelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                updateFilesList(selectedOption);
            });
        });

        function previewFile(fileId) {
            fetch(`../api/handle_file.php?action=preview&id=${fileId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.open(data.viewer_url, '_blank');
                    } else {
                        alert('Error loading preview: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading file preview');
                });
        }
    </script>
</body>
</html> 