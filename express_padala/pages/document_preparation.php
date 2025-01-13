<?php
require_once '../config/database.php';

// Get next reference number
$sql = "SELECT sequence_number FROM reference_sequence LIMIT 1";
$result = $conn->query($sql);
$row = $result->fetch_assoc();
$nextSequence = $row['sequence_number'];
$nextReference = "REF-" . date('mdY') . "-" . str_pad($nextSequence, 5, '0', STR_PAD_LEFT);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Preparation - Express Padala</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        .document-type-checkbox {
            margin-right: 20px;
            margin-bottom: 10px;
        }
        .reference-display {
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        /* Disable form while submitting */
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
                            <i class="bi bi-file-earmark-plus me-2"></i>Document Preparation
                        </h5>
                    </div>
                    <div class="card-body">
                        <!-- Success Message Container -->
                        <div id="success-message" class="alert alert-success" style="display: none;">
                            Document saved successfully!<br>
                            Reference Number: <strong id="reference-number"></strong><br>
                            <a href="#" id="track-link" class="alert-link">Track this document</a>
                            <button type="button" class="btn-close float-end" aria-label="Close"></button>
                        </div>

                        <form id="documentForm" class="offline-form" data-store="documents" action="../api/save_document.php" method="POST">
                            <div class="mb-3">
                                <label class="form-label">Reference Number</label>
                                <div class="reference-display" id="reference-display">
                                    <?php echo $nextReference; ?>
                                </div>
                                <input type="hidden" id="main_reference" name="reference_number" value="<?php echo $nextReference; ?>">
                            </div>

                            <div class="mb-3">
                                <label for="sender_name" class="form-label">Sender's Name</label>
                                <input type="text" class="form-control" id="sender_name" name="sender_name" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Document Types</label>
                                <div class="document-types-container">
                                    <div class="document-type-checkbox">
                                        <input type="checkbox" class="form-check-input" id="type_remittances" name="document_types[]" value="remittances">
                                        <label class="form-check-label" for="type_remittances">Remittances</label>
                                    </div>
                                    <div class="document-type-checkbox">
                                        <input type="checkbox" class="form-check-input" id="type_cv" name="document_types[]" value="cv_number">
                                        <label class="form-check-label" for="type_cv">CV Number</label>
                                    </div>
                                    <div class="document-type-checkbox">
                                        <input type="checkbox" class="form-check-input" id="type_jv" name="document_types[]" value="jv_number">
                                        <label class="form-check-label" for="type_jv">JV Number</label>
                                    </div>
                                    <div class="document-type-checkbox">
                                        <input type="checkbox" class="form-check-input" id="type_supplies" name="document_types[]" value="supplies">
                                        <label class="form-check-label" for="type_supplies">Supplies</label>
                                    </div>
                                    <div class="document-type-checkbox">
                                        <input type="checkbox" class="form-check-input" id="type_folders" name="document_types[]" value="folders">
                                        <label class="form-check-label" for="type_folders">Loan Folder/s</label>
                                    </div>
                                </div>
                                <div class="invalid-feedback">Please select at least one document type.</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Attach Files</label>
                                <div class="input-group">
                                    <input type="file" class="form-control" id="fileInput" accept=".pdf,.doc,.docx,.xls,.xlsx,.txt" multiple>
                                    <button type="button" class="btn btn-outline-secondary" id="uploadBtn">
                                        <span class="spinner-border spinner-border-sm d-none" role="status"></span>
                                        Upload
                                    </button>
                                </div>
                                <div class="form-text">Supported formats: PDF, Word, Excel, Text files</div>
                                <div id="filesList" class="list-group mt-2"></div>
                                <input type="hidden" id="uploadedFiles" name="uploaded_files" required>
                                <div class="invalid-feedback">Please upload at least one file.</div>
                            </div>

                            <button type="submit" class="btn btn-primary" id="submitBtn" disabled>
                                <span class="spinner-border" role="status" aria-hidden="true"></span>
                                Save Document
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
            const form = document.getElementById('documentForm');
            const successMessage = document.getElementById('success-message');
            const referenceNumber = document.getElementById('reference-number');
            const trackLink = document.getElementById('track-link');
            const referenceDisplay = document.getElementById('reference-display');
            const mainReference = document.getElementById('main_reference');
            const checkboxes = document.querySelectorAll('input[type="checkbox"]');
            const submitBtn = document.getElementById('submitBtn');
            const spinner = submitBtn.querySelector('.spinner-border');
            const uploadedFilesInput = document.getElementById('uploadedFiles');
            let isSubmitting = false;
            let uploadedFiles = [];

            // Function to validate form
            function validateForm() {
                const isCheckboxValid = Array.from(checkboxes).some(cb => cb.checked);
                const isFilesValid = uploadedFiles.length > 0;
                const isSenderValid = document.getElementById('sender_name').value.trim() !== '';

                submitBtn.disabled = !(isCheckboxValid && isFilesValid && isSenderValid);
                
                // Show/hide validation messages
                const filesContainer = uploadedFilesInput.closest('.mb-3');
                if (!isFilesValid) {
                    filesContainer.classList.add('was-validated');
                } else {
                    filesContainer.classList.remove('was-validated');
                }
            }

            // Add validation to checkboxes
            checkboxes.forEach(cb => {
                cb.addEventListener('change', validateForm);
            });

            // Add validation to sender name
            document.getElementById('sender_name').addEventListener('input', validateForm);

            // Close success message
            document.querySelector('.btn-close')?.addEventListener('click', function() {
                successMessage.style.display = 'none';
            });

            function getFileIcon(type) {
                switch(type.toLowerCase()) {
                    case 'pdf': return 'pdf';
                    case 'doc':
                    case 'docx': return 'word';
                    case 'xls':
                    case 'xlsx': return 'excel';
                    case 'txt': return 'text';
                    default: return 'file';
                }
            }

            function formatFileSize(bytes) {
                if (bytes === 0) return '0 Bytes';
                const k = 1024;
                const sizes = ['Bytes', 'KB', 'MB', 'GB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));
                return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
            }

            function updateFilesList() {
                const filesList = document.getElementById('filesList');
                
                filesList.innerHTML = uploadedFiles.map(file => `
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <i class="bi bi-file-earmark-${getFileIcon(file.type)}"></i> 
                                ${file.name}
                                <br>
                                <small class="text-muted">
                                    Type: ${file.type.toUpperCase()} | 
                                    Size: ${formatFileSize(file.size)}
                                </small>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeFile(${file.id})">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                `).join('');
                
                uploadedFilesInput.value = JSON.stringify(uploadedFiles.map(f => f.id));
                validateForm(); // Revalidate form after files list update
            }

            window.removeFile = function(fileId) {
                uploadedFiles = uploadedFiles.filter(f => f.id !== fileId);
                updateFilesList();
            }

            document.getElementById('uploadBtn').addEventListener('click', async function() {
                const fileInput = document.getElementById('fileInput');
                const files = fileInput.files;
                
                if (files.length === 0) {
                    alert('Please select files to upload');
                    return;
                }

                const spinner = this.querySelector('.spinner-border');
                const formData = new FormData();
                
                for (let file of files) {
                    formData.append('files[]', file);
                }

                try {
                    spinner.classList.remove('d-none');
                    this.disabled = true;
                    fileInput.disabled = true;

                    const response = await fetch('../api/handle_file.php?action=upload', {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();
                    
                    if (result.success) {
                        uploadedFiles = [...uploadedFiles, ...result.files];
                        updateFilesList();
                        fileInput.value = '';
                    } else {
                        alert('Error uploading files: ' + result.message);
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('An error occurred while uploading files');
                } finally {
                    spinner.classList.add('d-none');
                    this.disabled = false;
                    fileInput.disabled = false;
                }
            });

            // Handle form submission
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                if (isSubmitting) {
                    return;
                }

                // Validate checkboxes
                const isCheckboxValid = Array.from(checkboxes).some(cb => cb.checked);
                if (!isCheckboxValid) {
                    alert('Please select at least one document type.');
                    return;
                }

                // Validate files
                if (uploadedFiles.length === 0) {
                    alert('Please upload at least one file.');
                    return;
                }

                // Disable form and show spinner
                isSubmitting = true;
                form.classList.add('form-submitting');
                spinner.style.display = 'inline-block';
                submitBtn.disabled = true;
                
                const formData = new FormData(form);
                
                fetch(form.action, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        // Show success message
                        successMessage.style.display = 'block';
                        referenceNumber.textContent = result.reference_number;
                        trackLink.href = `track.php?ref=${result.reference_number}`;
                        
                        // Reset form
                        form.reset();
                        uploadedFiles = [];
                        updateFilesList();
                        
                        // Redirect to home page after 1.5 seconds
                        setTimeout(() => {
                            window.location.href = '/express_padala/index.php';
                        }, 1500);
                    } else {
                        alert('Error: ' + result.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while saving the document.');
                })
                .finally(() => {
                    // Re-enable form
                    isSubmitting = false;
                    form.classList.remove('form-submitting');
                    spinner.style.display = 'none';
                    submitBtn.disabled = false;
                });
            });
        });
    </script>
</body>
</html> 