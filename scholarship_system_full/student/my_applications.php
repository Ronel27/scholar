<?php
session_start();
require_once '../config/Database.php';

// Ensure student is logged in
if(!isset($_SESSION['role']) || $_SESSION['role'] != 'student'){
    header('Location: ../index.php'); 
    exit;
}

$db = (new Database())->connect();

// Get student_id
$stmt = $db->prepare("SELECT student_id FROM students WHERE user_id=?");
$stmt->execute([$_SESSION['user_id']]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);
$student_id = $student['student_id'];

// Fetch all applications for this student
$stmt = $db->prepare("
    SELECT a.application_id, a.date_applied, a.status, a.remarks, a.documents,
           s.name AS scholarship_name, s.sponsor
    FROM applications a
    JOIN scholarships s ON a.scholarship_id = s.scholarship_id
    WHERE a.student_id = ?
    ORDER BY a.date_applied DESC
");
$stmt->execute([$student_id]);
$applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>

<div class="container mt-4">
    <h3>My Applications</h3>

    <?php if (empty($applications)): ?>
    <div class="alert alert-info">You have not applied for any scholarships yet.</div>
<?php else: ?>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>ID</th>
                <th>Scholarship</th>
                <th>Sponsor</th>
                <th>Date Applied</th>
                <th>Status</th>
                <th>Documents / Remarks</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $counter = 1;
            foreach($applications as $app): ?>
                <tr>
                    <td><?= $counter++ ?></td>
                    <td><?= htmlspecialchars($app['scholarship_name']) ?></td>
                    <td><?= htmlspecialchars($app['sponsor']) ?></td>
                    <td><?= $app['date_applied'] ?></td>
                    <td>
                        <?php
                            $status_class = match($app['status']){
                                'Pending' => 'badge bg-warning',
                                'Approved' => 'badge bg-success',
                                'Rejected' => 'badge bg-danger',
                                'For Interview' => 'badge bg-info text-dark',
                                default => 'badge bg-secondary'
                            };
                        ?>
                        <span class="<?= $status_class ?>"><?= htmlspecialchars($app['status']) ?></span>
                    </td>
                    <td><?= htmlspecialchars($app['remarks'] ?? 'N/A') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <!-- Back Button -->
    <a href="<?= $_SERVER['HTTP_REFERER'] ?? '#' ?>" class="btn btn-secondary mt-3">Back</a>
<?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
