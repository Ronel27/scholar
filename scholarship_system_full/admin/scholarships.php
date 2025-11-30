<?php
session_start();
require_once '../config/Database.php';

if(!isset($_SESSION['role']) || $_SESSION['role'] != 'admin'){
    header('Location: ../index.php'); 
    exit;
}

$db = (new Database())->connect();
$errors = [];
$success = '';

// --- Handle Add Scholarship ---
if(isset($_POST['add_scholarship'])){
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $requirements = trim($_POST['requirements']);
    $sponsor = trim($_POST['sponsor']);
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $amount = $_POST['amount'];
    $status = $_POST['status'];

    // Prevent duplicate scholarship (name + sponsor)
    $check = $db->prepare("SELECT * FROM scholarships WHERE name=? AND sponsor=?");
    $check->execute([$name, $sponsor]);
    if($check->fetch()){
        $errors[] = "Scholarship with this name and sponsor already exists.";
    } else {
        $stmt = $db->prepare("
            INSERT INTO scholarships (name, description, requirements, sponsor, start_date, end_date, amount, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$name, $description, $requirements, $sponsor, $start_date, $end_date, $amount, $status]);
        $success = "Scholarship added successfully!";
    }
}

// --- Handle Edit Scholarship ---
if(isset($_POST['update_scholarship'])){
    $id = $_POST['scholarship_id'];
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $requirements = trim($_POST['requirements']);
    $sponsor = trim($_POST['sponsor']);
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $amount = $_POST['amount'];
    $status = $_POST['status'];

    $stmt = $db->prepare("
        UPDATE scholarships
        SET name = ?, description = ?, requirements = ?, sponsor = ?, start_date = ?, end_date = ?, amount = ?, status = ?
        WHERE scholarship_id = ?
    ");
    $stmt->execute([$name, $description, $requirements, $sponsor, $start_date, $end_date, $amount, $status, $id]);
    $success = "Scholarship updated successfully!";
}

// --- Handle Delete Scholarship ---
if(isset($_GET['delete'])){
    $id = $_GET['delete'];
    $stmt = $db->prepare("DELETE FROM scholarships WHERE scholarship_id=?");
    $stmt->execute([$id]);
    $success = "Scholarship deleted successfully!";
}

// Fetch all scholarships
$scholarships = $db->query("SELECT * FROM scholarships ORDER BY start_date DESC")->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>

<div class="container mt-4">
    <h3>Manage Scholarships</h3>

    <?php foreach($errors as $e) echo '<div class="alert alert-danger">'.$e.'</div>'; ?>
    <?php if($success) echo '<div class="alert alert-success">'.$success.'</div>'; ?>

    <!-- Back button using JavaScript -->
    <button type="button" onclick="history.back()" class="btn btn-secondary mb-3">Back</button>

    <!-- Add Scholarship Form -->
    <div class="card mb-4">
        <div class="card-header">Add New Scholarship</div>
        <div class="card-body">
            <form method="post">
                <div class="mb-2">
                    <input type="text" name="name" class="form-control" placeholder="Scholarship Name" required>
                </div>
                <div class="mb-2">
                    <textarea name="description" class="form-control" placeholder="Description"></textarea>
                </div>
                <div class="mb-2">
                    <textarea name="requirements" class="form-control" placeholder="Requirements"></textarea>
                </div>
                <div class="mb-2">
                    <input type="text" name="sponsor" class="form-control" placeholder="Sponsor" required>
                </div>
                <div class="row mb-2">
                    <div class="col">
                        <input type="date" name="start_date" class="form-control" required>
                    </div>
                    <div class="col">
                        <input type="date" name="end_date" class="form-control" required>
                    </div>
                </div>
                <div class="mb-2">
                    <input type="number" step="0.01" name="amount" class="form-control" placeholder="Amount" required>
                </div>
                <div class="mb-2">
                    <select name="status" class="form-control" required>
                        <option value="Open">Open</option>
                        <option value="Closed">Closed</option>
                    </select>
                </div>
                <button type="submit" name="add_scholarship" class="btn btn-success">Add Scholarship</button>
            </form>
        </div>
    </div>

    <!-- Scholarships Table -->
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Sponsor</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $counter = 1;
            foreach($scholarships as $s): ?>
                <tr>
                    <td><?= $counter++ ?></td>
                    <td><?= htmlspecialchars($s['name']) ?></td>
                    <td><?= htmlspecialchars($s['sponsor']) ?></td>
                    <td><?= $s['amount'] ?></td>
                    <td><?= $s['status'] ?></td>
                    <td>
                        <!-- Edit Button triggers modal -->
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editModal<?= $s['scholarship_id'] ?>">Edit</button>
                        <a href="?delete=<?= $s['scholarship_id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this scholarship?')">Delete</a>
                    </td>
                </tr>

                <!-- Edit Modal -->
                <div class="modal fade" id="editModal<?= $s['scholarship_id'] ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form method="post">
                                <div class="modal-header">
                                    <h5 class="modal-title">Edit Scholarship</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <input type="hidden" name="scholarship_id" value="<?= $s['scholarship_id'] ?>">
                                    <div class="mb-2">
                                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($s['name']) ?>" required>
                                    </div>
                                    <div class="mb-2">
                                        <textarea name="description" class="form-control"><?= htmlspecialchars($s['description']) ?></textarea>
                                    </div>
                                    <div class="mb-2">
                                        <textarea name="requirements" class="form-control"><?= htmlspecialchars($s['requirements']) ?></textarea>
                                    </div>
                                    <div class="mb-2">
                                        <input type="text" name="sponsor" class="form-control" value="<?= htmlspecialchars($s['sponsor']) ?>" required>
                                    </div>
                                    <div class="row mb-2">
                                        <div class="col">
                                            <input type="date" name="start_date" class="form-control" value="<?= $s['start_date'] ?>" required>
                                        </div>
                                        <div class="col">
                                            <input type="date" name="end_date" class="form-control" value="<?= $s['end_date'] ?>" required>
                                        </div>
                                    </div>
                                    <div class="mb-2">
                                        <input type="number" step="0.01" name="amount" class="form-control" value="<?= $s['amount'] ?>" required>
                                    </div>
                                    <div class="mb-2">
                                        <select name="status" class="form-control" required>
                                            <option value="Open" <?= $s['status']=='Open'?'selected':'' ?>>Open</option>
                                            <option value="Closed" <?= $s['status']=='Closed'?'selected':'' ?>>Closed</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="submit" name="update_scholarship" class="btn btn-primary">Update</button>
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

<!-- Bootstrap JS (required for modal) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<?php include '../includes/footer.php'; ?>
