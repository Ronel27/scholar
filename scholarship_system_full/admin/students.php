<?php
session_start();
require_once '../config/Database.php';

// Restrict access to admin only
if(!isset($_SESSION['role']) || $_SESSION['role'] != 'admin'){
    header('Location: ../index.php');
    exit;
}

$db = (new Database())->connect();
$success = '';
$error = '';

// Handle student update
if (isset($_POST['update_student'])) {
    $student_id = $_POST['student_id'];
    $email = $_POST['email'];
    $contact_number = $_POST['contact_number'];
    $school_name = $_POST['school_name'];
    $course = $_POST['course'];
    $year_level = $_POST['year_level'];

    $stmt = $db->prepare("
        UPDATE students 
        SET email = ?, contact_number = ?, school_name = ?, course = ?, year_level = ?
        WHERE student_id = ?
    ");
    if ($stmt->execute([$email, $contact_number, $school_name, $course, $year_level, $student_id])) {
        $success = "Student record updated successfully!";
    } else {
        $error = "Failed to update student record.";
    }
}

// Handle student deletion
if (isset($_GET['delete'])) {
    $student_id = intval($_GET['delete']);
    
    // Optional: delete the user account as well
    $stmt = $db->prepare("DELETE FROM users WHERE user_id = (SELECT user_id FROM students WHERE student_id = ?)");
    $stmt->execute([$student_id]);

    $stmt2 = $db->prepare("DELETE FROM students WHERE student_id = ?");
    if ($stmt2->execute([$student_id])) {
        $success = "Student deleted successfully!";
    } else {
        $error = "Failed to delete student.";
    }
}

// Fetch all students
$students = $db->query("
    SELECT st.student_id, u.username, st.first_name, st.last_name, 
           st.email, st.contact_number, st.school_name, st.course, st.year_level
    FROM students st
    JOIN users u ON st.user_id = u.user_id
    ORDER BY st.last_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>

<div class="container mt-4">
    <h3 class="mb-3">Manage Students</h3>

    <!-- Back button -->
    <a href="dashboard.php" class="btn btn-secondary mb-3">← Back</a>

    <!-- Success or error message -->
    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php elseif ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <table class="table table-bordered table-hover align-middle">
        <thead class="table-dark">
            <tr>
                <th>Username</th>
                <th>Full Name</th>
                <th>Email</th>
                <th>Contact</th>
                <th>School</th>
                <th>Course</th>
                <th>Year Level</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($students): ?>
            <?php foreach($students as $s): ?>
            <tr>
                <td><?= htmlspecialchars($s['username'] ?: 'N/A') ?></td>
                <td><?= htmlspecialchars(trim($s['first_name'].' '.$s['last_name']) ?: 'N/A') ?></td>
                <td><?= htmlspecialchars($s['email'] ?: 'N/A') ?></td>
                <td><?= htmlspecialchars($s['contact_number'] ?: 'N/A') ?></td>
                <td><?= htmlspecialchars($s['school_name'] ?: 'N/A') ?></td>
                <td><?= htmlspecialchars($s['course'] ?: 'N/A') ?></td>
                <td><?= htmlspecialchars($s['year_level'] ?: 'N/A') ?></td>
                <td>
                    <!-- Edit Button -->
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editModal<?= $s['student_id'] ?>">Edit</button>
                    <!-- Delete Button -->
                    <a href="?delete=<?= $s['student_id'] ?>" 
                       class="btn btn-danger btn-sm" 
                       onclick="return confirm('Are you sure you want to delete this student?');">Delete</a>
                </td>
            </tr>

            <!-- Edit Modal -->
            <div class="modal fade" id="editModal<?= $s['student_id'] ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="post">
                            <div class="modal-header">
                                <h5 class="modal-title">Edit Student Information</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <input type="hidden" name="student_id" value="<?= $s['student_id'] ?>">

                                <div class="mb-2">
                                    <label>Email</label>
                                    <input type="email" name="email" value="<?= htmlspecialchars($s['email']) ?>" class="form-control" required>
                                </div>

                                <div class="mb-2">
                                    <label>Contact Number</label>
                                    <input type="text" name="contact_number" value="<?= htmlspecialchars($s['contact_number']) ?>" class="form-control">
                                </div>

                                <div class="mb-2">
                                    <label>School Name</label>
                                    <input type="text" name="school_name" value="<?= htmlspecialchars($s['school_name']) ?>" class="form-control">
                                </div>

                                <div class="mb-2">
                                    <label>Course</label>
                                    <input type="text" name="course" value="<?= htmlspecialchars($s['course']) ?>" class="form-control">
                                </div>

                                <div class="mb-2">
                                    <label>Year Level</label>
                                    <input type="text" name="year_level" value="<?= htmlspecialchars($s['year_level']) ?>" class="form-control">
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="submit" name="update_student" class="btn btn-success">Save Changes</button>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="8" class="text-center text-muted">No students found.</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<?php include '../includes/footer.php'; ?>
