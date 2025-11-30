<?php
// This script marks all pending applications as seen by the admin.
session_start();
require_once '../config/Database.php';

// Security check
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    http_response_code(403);
    exit;
}

try {
    $db = (new Database())->connect();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Update all 'Pending' applications to set admin_seen = TRUE
    $stmt = $db->prepare("
        UPDATE applications 
        SET admin_seen = TRUE 
        WHERE status = 'Pending' AND admin_seen = FALSE
    ");
    $stmt->execute();
    
    // Send a success JSON response
    echo json_encode(['success' => true, 'updated_count' => $stmt->rowCount()]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>