<?php
session_start();
require_once '../config/Database.php';

if(!isset($_SESSION['role']) || $_SESSION['role'] != 'student'){
    header('Location: ../index.php'); exit;
}

$db = (new Database())->connect();

// Get student_id
$stmt = $db->prepare("SELECT student_id FROM students WHERE user_id=?");
$stmt->execute([$_SESSION['user_id']]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);
$student_id = $student['student_id'];

// Count total applications
$total_apps = $db->prepare("SELECT COUNT(*) FROM applications WHERE student_id=?");
$total_apps->execute([$student_id]);
$total_apps_count = $total_apps->fetchColumn();

// Count approved applications
$approved_apps = $db->prepare("SELECT COUNT(*) FROM applications WHERE student_id=? AND status='Approved'");
$approved_apps->execute([$student_id]);
$approved_count = $approved_apps->fetchColumn();

// Count pending applications
$pending_apps_count = $total_apps_count - $approved_count;

include '../includes/header.php';
?>

<div class="container mt-4">
    <h3>Student Dashboard</h3>
    <div class="row mt-3">
        <div class="col-md-4"><div class="card p-3 bg-primary text-white">Total Applications<br><?= $total_apps_count ?></div></div>
        <div class="col-md-4"><div class="card p-3 bg-success text-white">Approved Applications<br><?= $approved_count ?></div></div>
        <div class="col-md-4"><div class="card p-3 bg-warning text-dark">Pending Applications<br><?= $pending_apps_count ?></div></div>
    </div>
    <div class="mt-4">
        <a href="apply.php" class="btn btn-primary">Apply for Scholarship</a>
        <a href="my_applications.php" class="btn btn-info">My Applications</a>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
