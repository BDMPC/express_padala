<?php
require_once '../config/database.php';

header('Content-Type: application/json');

function generateUniqueFileName($originalName, $uploadDir) {
    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
    $fileName = uniqid() . '_' . time() . '.' . $extension;
    while (file_exists($uploadDir . '/' . $fileName)) {
        $fileName = uniqid() . '_' . time() . '.' . $extension;
    }
    return $fileName;
}

function getMimeType($file) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file);
    finfo_close($finfo);
    return $mimeType;
}

try {
    $action = $_POST['action'] ?? ($_GET['action'] ?? null);
    
    if (!$action) {
        throw new Exception("Action is required");
    }

    switch ($action) {
        case 'upload':
            if (!isset($_FILES['files']) || empty($_FILES['files']['name'][0])) {
                throw new Exception("No files uploaded");
            }

            $uploadDir = __DIR__ . '/../uploads';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $uploadedFiles = [];
            $files = $_FILES['files'];
            
            // Prepare temporary insert statement
            $stmt = $conn->prepare("INSERT INTO document_files (file_name, original_name, file_path, file_type, file_size, mime_type) VALUES (?, ?, ?, ?, ?, ?)");

            for ($i = 0; $i < count($files['name']); $i++) {
                if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                    continue;
                }

                $originalName = $files['name'][$i];
                $fileName = generateUniqueFileName($originalName, $uploadDir);
                $filePath = $uploadDir . '/' . $fileName;
                
                if (move_uploaded_file($files['tmp_name'][$i], $filePath)) {
                    $fileType = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                    $fileSize = $files['size'][$i];
                    $mimeType = getMimeType($filePath);

                    $stmt->bind_param("ssssss", 
                        $fileName,
                        $originalName,
                        $filePath,
                        $fileType,
                        $fileSize,
                        $mimeType
                    );

                    if ($stmt->execute()) {
                        $uploadedFiles[] = [
                            'id' => $stmt->insert_id,
                            'name' => $originalName,
                            'type' => $fileType,
                            'size' => $fileSize
                        ];
                    }
                }
            }

            echo json_encode([
                'success' => true,
                'files' => $uploadedFiles
            ]);
            break;

        case 'preview':
            if (!isset($_GET['id'])) {
                throw new Exception("File ID is required");
            }

            $file_id = intval($_GET['id']);
            
            // Get file details
            $stmt = $conn->prepare("SELECT * FROM document_files WHERE id = ? AND status = 'active'");
            $stmt->bind_param("i", $file_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                throw new Exception("File not found");
            }

            $file = $result->fetch_assoc();
            
            if (!file_exists($file['file_path'])) {
                throw new Exception("File not found on server");
            }

            $file_type = strtolower($file['file_type']);
            $viewer_url = '../viewer.php?id=' . $file_id . '&type=';
            
            switch ($file_type) {
                case 'pdf':
                    $viewer_url .= 'pdf';
                    break;
                case 'doc':
                case 'docx':
                    $viewer_url .= 'word';
                    break;
                case 'xls':
                case 'xlsx':
                    $viewer_url .= 'excel';
                    break;
                case 'txt':
                default:
                    $viewer_url .= 'text';
                    break;
            }
            
            echo json_encode([
                'success' => true,
                'viewer_url' => $viewer_url,
                'filename' => $file['original_name'],
                'type' => $file_type
            ]);
            break;

        case 'download':
            if (!isset($_GET['id'])) {
                throw new Exception("File ID is required");
            }

            $file_id = intval($_GET['id']);
            
            // Get file details
            $stmt = $conn->prepare("SELECT * FROM document_files WHERE id = ? AND status = 'active'");
            $stmt->bind_param("i", $file_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                throw new Exception("File not found");
            }

            $file = $result->fetch_assoc();
            
            if (!file_exists($file['file_path'])) {
                throw new Exception("File not found on server");
            }

            // Set headers for download
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $file['original_name'] . '"');
            header('Content-Length: ' . $file['file_size']);
            header('Cache-Control: no-cache, must-revalidate');
            header('Pragma: public');
            
            // Clear output buffer
            ob_clean();
            flush();
            
            // Output file
            readfile($file['file_path']);
            exit;

        default:
            throw new Exception("Invalid action");
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?> 