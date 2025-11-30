<?php
session_start();
require_once '../config/Database.php';

// --- Define the Qualification Limits ---
// The income limit for filtering applications displayed to the admin
$income_limit = 20000;
// The GWA/GPA limit (1.0 is highest, 5.0 is lowest)
$gwa_limit = 2.50; 

// Check if the user is logged in and is an admin
if(!isset($_SESSION['role']) || $_SESSION['role'] != 'admin'){
    header('Location: ../index.php'); 
    exit;
}

$db = (new Database())->connect();
$success = '';
$errors = [];

// Handle status update (Approve / Reject)
if (isset($_POST['action_type']) && isset($_POST['application_id'])) {
    $application_id = $_POST['application_id'];
    $status = $_POST['action_type'] === 'approve' ? 'Approved' : 'Rejected';

    try {
        $stmt = $db->prepare("UPDATE applications SET status=? WHERE application_id=?");
        $stmt->execute([$status, $application_id]);
        $success = "Application status updated to $status!";
    } catch (PDOException $e) {
        $errors[] = "Error updating status: " . $e->getMessage();
    }
}

// Handle edit/update from modal
if(isset($_POST['update_application'])){
    $application_id = $_POST['application_id'];
    $status = $_POST['status'];
    $remarks = $_POST['remarks'];

    try {
        $stmt = $db->prepare("UPDATE applications SET status=?, remarks=? WHERE application_id=?");
        $stmt->execute([$status, $remarks, $application_id]);
        $success = "Application updated successfully!";
    } catch (PDOException $e) {
        $errors[] = "Error updating application: " . $e->getMessage();
    }
}

// 🔑 UPDATED FETCH APPLICATIONS WITH INCOME AND GWA/GPA CONDITION 🔑
try {
    $stmt = $db->prepare("
        SELECT 
            a.application_id, 
            st.first_name, 
            st.last_name, 
            sch.name AS scholarship_name,
            a.remarks, 
            a.status, 
            a.date_applied,
            st.family_income, 
            st.gpa 
        FROM applications a
        JOIN students st ON a.student_id = st.student_id
        JOIN scholarships sch ON a.scholarship_id = sch.scholarship_id
        -- Filter condition: show applications meeting BOTH income and GWA criteria
        WHERE st.family_income <= :income_limit
        AND st.gpa <= :gwa_limit
        ORDER BY a.date_applied DESC
    ");
    
    // Bind parameters securely
    $stmt->bindParam(':income_limit', $income_limit, PDO::PARAM_INT);
    $stmt->bindParam(':gwa_limit', $gwa_limit, PDO::PARAM_STR);
    $stmt->execute();
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $errors[] = "Database Error: Could not fetch applications. " . $e->getMessage();
    $applications = []; 
}


include '../includes/header.php';
?>

<div class="container mt-4">
    <h3>Manage Qualified Applications</h3>

    <?php if($success) echo '<div class="alert alert-success">'.$success.'</div>'; ?>
    <?php foreach($errors as $e) echo '<div class="alert alert-danger">'.$e.'</div>'; ?>

    <button type="button" onclick="window.location.href='dashboard.php'" class="btn btn-secondary mb-3">← Back</button>

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Student</th>
                <th>Scholarship</th>
                <th>Status</th>
                <th>Date Applied</th>
                <th>Family Income</th> 
                <th>GWA/GPA</th> <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($applications)): ?>
            <tr>
                <td colspan="7" class="text-center">No qualified applications found matching the income and GWA/GPA criteria.</td>
            </tr>
        <?php endif; ?>

        <?php foreach($applications as $a): ?>
        <tr>
            <td><?= htmlspecialchars($a['first_name'].' '.$a['last_name']) ?></td>
            <td><?= htmlspecialchars($a['scholarship_name']) ?></td>
            <td>
                <?php 
                $badgeClass = match($a['status']) {
                    'Approved' => 'bg-success',
                    'Rejected' => 'bg-danger',
                    'For Interview' => 'bg-warning text-dark',
                    default => 'bg-secondary'
                };
                ?>
                <span class="badge <?= $badgeClass ?>"><?= $a['status'] ?></span>
            </td>
            <td><?= $a['date_applied'] ?></td>
            <td>₱<?= number_format($a['family_income'], 2) ?></td> 
            <td><?= number_format($a['gpa'], 2) ?></td> <td>
                <form method="post" class="d-inline">
                    <input type="hidden" name="application_id" value="<?= $a['application_id'] ?>">
                    
                    <?php if ($a['status'] == 'Pending' || $a['status'] == 'Rejected'): ?>
                        <button type="submit" name="action_type" value="approve" class="btn btn-success btn-sm">Approve</button>
                    <?php endif; ?>

                    <?php if ($a['status'] == 'Pending' || $a['status'] == 'Approved'): ?>
                        <button type="submit" name="action_type" value="reject" class="btn btn-danger btn-sm">Reject</button>
                    <?php endif; ?>
                    
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editModal<?= $a['application_id'] ?>">Edit</button>
                </form>
            </td>
        </tr>

        <div class="modal fade" id="editModal<?= $a['application_id'] ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="post">
                        <div class="modal-header">
                            <h5 class="modal-title">Edit Application</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="application_id" value="<?= $a['application_id'] ?>">
                            <p><strong>Student:</strong> <?= htmlspecialchars($a['first_name'].' '.$a['last_name']) ?></p>
                            <p><strong>Scholarship:</strong> <?= htmlspecialchars($a['scholarship_name']) ?></p>
                            <hr>
                            <div class="mb-2">
                                <label>Status</label>
                                <select name="status" class="form-control" required>
                                    <option value="Pending" <?= $a['status']=='Pending'?'selected':'' ?>>Pending</option>
                                    <option value="Approved" <?= $a['status']=='Approved'?'selected':'' ?>>Approved</option>
                                    <option value="Rejected" <?= $a['status']=='Rejected'?'selected':'' ?>>Rejected</option>
                                    <option value="For Interview" <?= $a['status']=='For Interview'?'selected':'' ?>>For Interview</option>
                                </select>
                            </div>
                            <div class="mb-2">
                                <label>Remarks</label>
                                <textarea name="remarks" class="form-control"><?= htmlspecialchars($a['remarks']) ?></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="submit" name="update_application" class="btn btn-primary">Update</button>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<?php include '../includes/footer.php'; ?>