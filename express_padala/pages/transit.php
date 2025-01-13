<?php
require_once '../config/database.php';

// Get available documents that haven't been assigned to transit
$sql = "SELECT d.id, d.reference_number, d.sender_name, 
        GROUP_CONCAT(DISTINCT dt.type_name) as document_types,
        GROUP_CONCAT(DISTINCT df.id, ':', df.original_name, ':', df.file_type SEPARATOR '|') as files
        FROM documents d 
        LEFT JOIN document_types dt ON d.id = dt.document_id 
        LEFT JOIN document_files df ON d.id = df.document_id AND df.status = 'active'
        LEFT JOIN transits t ON d.id = t.document_id 
        WHERE t.id IS NULL 
        GROUP BY d.id
        ORDER BY d.sent_datetime DESC";
$result = $conn->query($sql);

// Get all branches
$branches_sql = "SELECT id, branch_name FROM branches ORDER BY branch_name";
$branches_result = $conn->query($branches_sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transit to Branches - Express Padala</title>
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
                            <i class="bi bi-truck me-2"></i>Transit to Branches
                        </h5>
                    </div>
                    <div class="card-body">
                        <!-- Success Message Container -->
                        <div id="success-message" class="alert alert-success" style="display: none;">
                            Transit details saved successfully!
                            <button type="button" class="btn-close float-end" aria-label="Close"></button>
                        </div>

                        <form id="transitForm" class="offline-form" data-store="transits" action="../api/save_transit.php" method="POST">
                            <div class="mb-3">
                                <label class="form-label">Select Documents</label>
                                <select class="form-select" name="document_ids[]" multiple required>
                                    <?php while($row = $result->fetch_assoc()): ?>
                                    <?php
                                    $files_html = '';
                                    if (!empty($row['files'])) {
                                        $files = array_map(function($file) {
                                            list($id, $name, $type) = explode(':', $file);
                                            return [
                                                'id' => $id,
                                                'name' => $name,
                                                'type' => $type
                                            ];
                                        }, explode('|', $row['files']));
                                        
                                        $files_html = '<div class="mt-1"><small class="text-muted">Attached Files:</small><br>';
                                        foreach ($files as $file) {
                                            $files_html .= sprintf(
                                                '<small><a href="#" onclick="previewFile(%d); return false;">%s</a></small><br>',
                                                $file['id'],
                                                htmlspecialchars($file['name'])
                                            );
                                        }
                                        $files_html .= '</div>';
                                    }
                                    ?>
                                    <option value="<?php echo $row['id']; ?>">
                                        REF: <?php echo $row['reference_number']; ?> - 
                                        Sender: <?php echo $row['sender_name']; ?> - 
                                        Types: <?php echo $row['document_types']; ?>
                                        <?php if (!empty($files_html)): ?>
                                        <?php echo $files_html; ?>
                                        <?php endif; ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                                <small class="form-text text-muted">Hold Ctrl/Cmd to select multiple documents</small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">BDMPC Sprinter/Rider Name</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                                    <input type="text" class="form-control" name="sprinter_rider_name" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Type of Transportation</label>
                                <select class="form-select" name="transportation_type" required>
                                    <option value="">Select Transportation</option>
                                    <option value="VAN">VAN</option>
                                    <option value="BUS">BUS</option>
                                    <option value="OTHERS">OTHERS</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Plate Number</label>
                                <input type="text" class="form-control" name="plate_number" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Driver/Conductor Name</label>
                                <input type="text" class="form-control" name="driver_name" required>
                            </div>

                            <div class="mb-3">
                                <label for="driver_contact" class="form-label">Driver's Contact Number (Optional)</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                                    <input type="tel" class="form-control" id="driver_contact" name="driver_contact" 
                                           pattern="^09[0-9]{9}$" 
                                           maxlength="11" 
                                           placeholder="09XXXXXXXXX"
                                           title="Please enter a valid Philippine mobile number (e.g., 09123456789)">
                                </div>
                                <div class="form-text">Enter a valid Philippine mobile number starting with 09 (11 digits)</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Destination Branch</label>
                                <select class="form-select" name="branch_id" required>
                                    <option value="">Select Branch</option>
                                    <?php while($branch = $branches_result->fetch_assoc()): ?>
                                    <option value="<?php echo $branch['id']; ?>">
                                        <?php echo htmlspecialchars($branch['branch_name']); ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Departure Date & Time</label>
                                <input type="datetime-local" class="form-control" name="departure_datetime" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Estimated Arrival Date & Time</label>
                                <input type="datetime-local" class="form-control" name="estimated_arrival" required>
                            </div>

                            <button type="submit" class="btn btn-primary" id="submitBtn">
                                <span class="spinner-border" role="status" aria-hidden="true"></span>
                                Save Transit Details
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
            const form = document.getElementById('transitForm');
            const successMessage = document.getElementById('success-message');
            const submitBtn = document.getElementById('submitBtn');
            const spinner = submitBtn.querySelector('.spinner-border');
            let isSubmitting = false;

            // Close success message
            document.querySelector('.btn-close')?.addEventListener('click', function() {
                successMessage.style.display = 'none';
            });

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
                            window.location.href = '/express_padala/index.php';
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
                    alert('An error occurred while saving transit details');
                    // Re-enable form on error
                    form.classList.remove('form-submitting');
                    spinner.style.display = 'none';
                    submitBtn.disabled = false;
                }

                isSubmitting = false;
            });

            // Set min datetime for departure and estimated arrival
            const departureDatetime = document.querySelector('input[name="departure_datetime"]');
            const estimatedArrival = document.querySelector('input[name="estimated_arrival"]');
            const now = new Date();
            now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
            
            // Set min datetime for departure to current time
            departureDatetime.min = now.toISOString().slice(0, 16);
            
            // Update estimated arrival min time when departure is changed
            departureDatetime.addEventListener('change', function() {
                estimatedArrival.min = this.value;
                if (estimatedArrival.value && estimatedArrival.value < this.value) {
                    estimatedArrival.value = this.value;
                }
            });
            
            // Initial setup for estimated arrival
            if (departureDatetime.value) {
                estimatedArrival.min = departureDatetime.value;
            } else {
                estimatedArrival.min = now.toISOString().slice(0, 16);
            }
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

        document.getElementById('driver_contact').addEventListener('input', function(e) {
            let value = e.target.value;
            
            // Remove any non-digit characters
            value = value.replace(/\D/g, '');
            
            // Ensure it starts with '09'
            if (value.length >= 2 && !value.startsWith('09')) {
                value = '09' + value.substring(2);
            }
            
            // Limit to 11 digits
            value = value.substring(0, 11);
            
            // Update the input value
            e.target.value = value;
        });

        document.querySelector('form').addEventListener('submit', function(e) {
            const contactInput = document.getElementById('driver_contact');
            if (contactInput.value) {
                const isValid = /^09[0-9]{9}$/.test(contactInput.value);
                if (!isValid) {
                    e.preventDefault();
                    alert('Please enter a valid Philippine mobile number (11 digits starting with 09)');
                    contactInput.focus();
                }
            }
        });
    </script>
</body>
</html> 