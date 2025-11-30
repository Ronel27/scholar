<?php
// This script runs every few seconds via AJAX to check for new applications
session_start();
require_once '../config/Database.php';

// Essential security check
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    http_response_code(403); 
    exit;
}

try {
    $db = (new Database())->connect();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    http_response_code(500);
    die("0");
}

// Fetch the count of Pending applications that the admin has NOT yet seen
try {
    $pending_apps = $db->query("
        SELECT COUNT(*) 
        FROM applications 
        WHERE status = 'Pending' 
        AND admin_seen = FALSE
    ")->fetchColumn();
} catch (Exception $e) {
    // If the admin_seen column is missing, this query will fail. 
    error_log("Notification count query failed: " . $e->getMessage());
    $pending_apps = 0;
}

// Output the count as a plain number for JavaScript to read
echo $pending_apps;
?>