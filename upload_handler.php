<?php
// upload_handler.php - Obsługa załączników do raportów
require_once 'config.php';

// Sprawdzenie autoryzacji
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    die(json_encode(['success' => false, 'error' => 'Unauthorized']));
}

header('Content-Type: application/json');

try {
    $pdo = getDB();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        
        // Upload files
        if (isset($_POST['action']) && $_POST['action'] === 'upload') {
            $report_id = intval($_POST['report_id']);
            $uploaded_files = [];
            
            if (!file_exists('uploads/')) {
                mkdir('uploads/', 0777, true);
            }
            
            if (!file_exists('uploads/reports/')) {
                mkdir('uploads/reports/', 0777, true);
            }
            
            foreach ($_FILES['files']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['files']['error'][$key] === UPLOAD_ERR_OK) {
                    $original_name = $_FILES['files']['name'][$key];
                    $file_size = $_FILES['files']['size'][$key];
                    $file_type = $_FILES['files']['type'][$key];
                    
                    // Validate file type
                    $allowed_types = [
                        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
                        'application/pdf', 'application/msword', 
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'text/plain', 'application/zip', 'video/mp4', 'video/avi'
                    ];
                    
                    if (!in_array($file_type, $allowed_types)) {
                        echo json_encode(['success' => false, 'error' => 'Nieprawidłowy typ pliku: ' . $original_name]);
                        exit;
                    }
                    
                    // Validate file size (max 10MB)
                    if ($file_size > 10 * 1024 * 1024) {
                        echo json_encode(['success' => false, 'error' => 'Plik zbyt duży: ' . $original_name]);
                        exit;
                    }
                    
                    // Generate unique filename
                    $file_extension = pathinfo($original_name, PATHINFO_EXTENSION);
                    $filename = uniqid() . '_' . time() . '.' . $file_extension;
                    $file_path = 'uploads/reports/' . $filename;
                    
                    if (move_uploaded_file($tmp_name, $file_path)) {
                        // Save to database
                        $stmt = $pdo->prepare("
                            INSERT INTO report_attachments 
                            (report_id, filename, original_filename, file_type, file_size) 
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([$report_id, $filename, $original_name, $file_type, $file_size]);
                        
                        $uploaded_files[] = [
                            'id' => $pdo->lastInsertId(),
                            'filename' => $filename,
                            'original_filename' => $original_name,
                            'file_type' => $file_type,
                            'file_size' => $file_size
                        ];
                    }
                }
            }
            
            echo json_encode(['success' => true, 'files' => $uploaded_files]);
        }
        
        // Get attachments for report
        if (isset($_POST['action']) && $_POST['action'] === 'get_attachments') {
            $report_id = intval($_POST['report_id']);
            
            $stmt = $pdo->prepare("
                SELECT id, filename, original_filename, file_type, file_size, uploaded_at 
                FROM report_attachments 
                WHERE report_id = ? 
                ORDER BY uploaded_at DESC
            ");
            $stmt->execute([$report_id]);
            $attachments = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'attachments' => $attachments]);
        }
        
        // Delete attachment
        if (isset($_POST['action']) && $_POST['action'] === 'delete_attachment') {
            $attachment_id = intval($_POST['attachment_id']);
            
            // Get file info first
            $stmt = $pdo->prepare("SELECT filename FROM report_attachments WHERE id = ?");
            $stmt->execute([$attachment_id]);
            $attachment = $stmt->fetch();
            
            if ($attachment) {
                // Delete file
                $file_path = 'uploads/reports/' . $attachment['filename'];
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
                
                // Delete from database
                $stmt = $pdo->prepare("DELETE FROM report_attachments WHERE id = ?");
                $stmt->execute([$attachment_id]);
                
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Attachment not found']);
            }
        }
    }
    
    // Serve file
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['file'])) {
        $filename = basename($_GET['file']);
        $file_path = 'uploads/reports/' . $filename;
        
        if (file_exists($file_path)) {
            // Get file info from database
            $stmt = $pdo->prepare("
                SELECT original_filename, file_type 
                FROM report_attachments 
                WHERE filename = ?
            ");
            $stmt->execute([$filename]);
            $file_info = $stmt->fetch();
            
            if ($file_info) {
                header('Content-Type: ' . $file_info['file_type']);
                header('Content-Disposition: inline; filename="' . $file_info['original_filename'] . '"');
                header('Content-Length: ' . filesize($file_path));
                
                readfile($file_path);
                exit;
            }
        }
        
        http_response_code(404);
        echo 'File not found';
        exit;
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>