<?php
session_start();
require_once '../config/Database.php';

// Define the qualification limits
$income_limit = 20000;
$gwa_limit = 2.50; // Qualified if GWA is 2.50 or lower

// Define the directory where documents will be stored
$upload_dir = '../uploads/applications/'; 

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

if (!$student) {
    echo "Error: Student profile not found.";
    exit;
}

$student_id = $student['student_id'];

$errors = [];
$success = '';

// Handle application submission
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    
    // --- 1. Collect POST Data ---
    $scholarship_id = $_POST['scholarship_id'] ?? null;
    $remarks = $_POST['remarks'] ?? '';
    
    // Use filter_var to safely handle input and convert to float/decimal
    $family_income = filter_var($_POST['family_income'] ?? 0, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $gpa = filter_var($_POST['gpa'] ?? 0, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    
    // --- 2. Validation Checks ---
    if(!$scholarship_id){
        $errors[] = "Please select a scholarship to apply.";
    }
    if (empty($family_income) || $family_income <= 0) {
        $errors[] = "Please enter a valid Parent's Monthly Income.";
    }
    if (empty($gpa) || $gpa < 1.00 || $gpa > 5.00) {
        $errors[] = "Please select a valid Current GWA/GPA.";
    }
    
    // --- 3. File Upload Handling (Optional) ---
    $document_paths = [];
    $files_selected = false;
    
    if (isset($_FILES['documents']) && is_array($_FILES['documents']['name'])) {
        $selected_files = array_filter($_FILES['documents']['name'], 'strlen');
        
        if (!empty($selected_files)) {
            $files_selected = true;

            if (!is_dir($upload_dir) && !mkdir($upload_dir, 0777, true)) { 
                $errors[] = "Error: Failed to create upload directory. Check permissions.";
            }

            foreach ($_FILES['documents']['name'] as $index => $fileName) {
                if (empty($fileName)) continue;

                if ($_FILES['documents']['error'][$index] !== UPLOAD_ERR_OK) {
                     $errors[] = "File upload error for " . htmlspecialchars($fileName) . " (Code: " . $_FILES['documents']['error'][$index] . ")";
                     continue;
                }

                $file_extension = pathinfo($fileName, PATHINFO_EXTENSION);
                $unique_name = $student_id . '_' . time() . '_' . $index . '.' . $file_extension;
                $target_file = $upload_dir . $unique_name;

                if (move_uploaded_file($_FILES['documents']['tmp_name'][$index], $target_file)) {
                    $document_paths[] = $target_file;
                } else {
                    $errors[] = "Failed to move uploaded file: " . htmlspecialchars($fileName);
                }
            }
            
            if ($files_selected && empty($document_paths) && empty($errors)) {
                 $errors[] = "Documents were selected, but none could be successfully processed. Please check file sizes.";
            }
        }
    }
    
    $documents_db_string = implode(', ', $document_paths);

    // --- 4. Submission Logic ---
    if(empty($errors)){
        try {
            // Check if student already applied
            $check = $db->prepare("SELECT * FROM applications WHERE scholarship_id=? AND student_id=?");
            $check->execute([$scholarship_id, $student_id]);
            
            if($check->fetch()){
                $errors[] = "You have already applied for this scholarship.";
            } else {
                
                // 1. Update students table with current income and GPA
                $stmt_det = $db->prepare("
                    UPDATE students 
                    SET family_income = ?, gpa = ? 
                    WHERE student_id = ?
                ");
                $stmt_det->execute([$family_income, $gpa, $student_id]);
                
                // 2. Insert into applications table
                $stmt_app = $db->prepare("
                    INSERT INTO applications (scholarship_id, student_id, documents, remarks)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt_app->execute([$scholarship_id, $student_id, $documents_db_string, $remarks]);

                $success = "Application submitted successfully!";
                $_POST = array(); 
            }
        } catch (PDOException $e) {
             $errors[] = "Application submission failed: " . $e->getMessage();
        }
    }
}

// Fetch all open scholarships
$scholarships = $db->query("SELECT DISTINCT scholarship_id, name, sponsor 
                             FROM scholarships 
                             WHERE status='Open' 
                             ORDER BY start_date DESC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch existing student data for pre-filling the form
$current_data = $db->prepare("SELECT family_income, gpa FROM students WHERE student_id = ?");
$current_data->execute([$student_id]);
$student_data = $current_data->fetch(PDO::FETCH_ASSOC);


include '../includes/header.php';
?>

<div class="container mt-4">
    <h3>Apply for Scholarship</h3>

    <?php if($success) echo '<div class="alert alert-success">'.$success.'</div>'; ?>
    <?php foreach($errors as $e) echo '<div class="alert alert-danger">'.$e.'</div>'; ?>

    <form method="post" enctype="multipart/form-data">
        <div class="mb-2">
            <label for="scholarship_id">Select Scholarship</label>
            <select name="scholarship_id" id="scholarship_id" class="form-control" required>
                <option value="">-- Select Scholarship --</option>
                <?php foreach($scholarships as $s): ?>
                    <option value="<?= $s['scholarship_id'] ?>" 
                        <?= isset($_POST['scholarship_id']) && $_POST['scholarship_id']==$s['scholarship_id'] ? 'selected':'' ?>>
                        <?= htmlspecialchars($s['name']) ?> (Sponsor: <?= htmlspecialchars($s['sponsor']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <hr>
        <h4>Financial & Academic Data</h4>
        
        <div class="mb-2">
            <label for="family_income">Parent's Monthly Income (₱)</label>
            <input type="number" step="100" min="0" name="family_income" id="family_income" class="form-control" required
                   value="<?= htmlspecialchars($_POST['family_income'] ?? $student_data['family_income'] ?? '') ?>">
            <small class="form-text text-muted">Enter the total monthly income of your parents/guardians.</small>
        </div>

        <div class="mb-2">
            <label for="gpa">Current GWA / GPA</label>
            <select name="gpa" id="gpa" class="form-control" required>
                <option value="">-- Select GWA/GPA --</option>
                <?php
                // Generate options: 1.0, 1.25, 1.50, ..., 5.00
                $gpa_options = [];
                for ($i = 1.00; $i <= 5.00; $i += 0.25) {
                    $gpa_options[] = number_format($i, 2);
                }
                $current_gpa_value = htmlspecialchars($_POST['gpa'] ?? $student_data['gpa'] ?? '');

                foreach ($gpa_options as $gpa_val):
                    $selected = ($gpa_val == $current_gpa_value) ? 'selected' : '';
                ?>
                    <option value="<?= $gpa_val ?>" <?= $selected ?>>
                        <?= $gpa_val ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small class="form-text text-muted">Select your GWA (1.0 is highest, 5.0 is lowest).
            </small>
        </div>
        
        <hr>
        <h4>Supporting Documents (Optional)</h4>

        <div class="mb-2">
            <label for="documents">Upload Documents / Requirements</label>
            <input type="file" name="documents[]" id="documents" class="form-control" multiple>
            <small class="form-text text-muted">Select all files required for the application. (Optional)</small>
        </div>
        
        <div class="mb-2">
            <label for="remarks">Remarks (optional)</label>
            <textarea name="remarks" id="remarks" class="form-control"><?= isset($_POST['remarks']) ? htmlspecialchars($_POST['remarks']) : '' ?></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Submit Application</button>
    </form>

    <?php if(isset($_SERVER['HTTP_REFERER'])): ?>
        <a href="dashboard.php" class="btn btn-secondary mt-3">Back</a>
    <?php endif; ?>

</div>

<?php include '../includes/footer.php'; ?>