<?php
require_once 'config/database.php';

if (!isset($_GET['id']) || !isset($_GET['type'])) {
    die('Missing required parameters');
}

$file_id = intval($_GET['id']);
$type = $_GET['type'];

// Get file details
$stmt = $conn->prepare("SELECT * FROM document_files WHERE id = ? AND status = 'active'");
$stmt->bind_param("i", $file_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die('File not found');
}

$file = $result->fetch_assoc();

if (!file_exists($file['file_path'])) {
    die('File not found on server');
}

// Handle different file types
switch ($type) {
    case 'pdf':
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $file['original_name'] . '"');
        break;
        
    case 'txt':
        // For text files, display in browser
        header('Content-Type: text/plain');
        header('Content-Disposition: inline; filename="' . $file['original_name'] . '"');
        break;
        
    case 'doc':
    case 'docx':
        // For Word files, use MS Office Online Viewer
        $fileUrl = urlencode('http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/api/handle_file.php?action=download&id=' . $file_id);
        header('Location: https://view.officeapps.live.com/op/view.aspx?src=' . $fileUrl);
        exit;
        
    case 'xls':
    case 'xlsx':
        // For Excel files, use MS Office Online Viewer
        $fileUrl = urlencode('http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/api/handle_file.php?action=download&id=' . $file_id);
        header('Location: https://view.officeapps.live.com/op/view.aspx?src=' . $fileUrl);
        exit;
        
    default:
        // For other files, force download
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $file['original_name'] . '"');
}

header('Content-Length: ' . filesize($file['file_path']));
header('Accept-Ranges: bytes');

// Clear any output buffers
ob_clean();
flush();

// Output the file
readfile($file['file_path']);
exit; 