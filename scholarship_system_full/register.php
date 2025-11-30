<?php
require_once 'config/Database.php';
$db = (new Database())->connect();

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $contact = trim($_POST['contact']);
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    $school_name = trim($_POST['school_name']);
    $course = trim($_POST['course']);
    $year_level = trim($_POST['year_level']);

    // Validation
    if (empty($first_name) || empty($last_name) || empty($email) || empty($contact) || empty($username) || empty($password) || empty($school_name) || empty($course) || empty($year_level)) {
        $errors[] = "All fields are required.";
    } elseif ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    } else {
        // Check for existing username or email
        $check = $db->prepare("SELECT * FROM users u JOIN students s ON u.user_id=s.user_id WHERE u.username=? OR s.email=?");
        $check->execute([$username, $email]);
        if ($check->fetch()) {
            $errors[] = "Username or email already exists.";
        }
    }

    // Insert into database
    if (empty($errors)) {
        // Create user account
        $stmt = $db->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'student')");
        $stmt->execute([$username, md5($password)]);
        $user_id = $db->lastInsertId();

        // Insert student info
        $stmt2 = $db->prepare("INSERT INTO students (user_id, first_name, last_name, email, contact_number, school_name, course, year_level) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt2->execute([$user_id, $first_name, $last_name, $email, $contact, $school_name, $course, $year_level]);

        $success = "Registration successful! You can now log in.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Registration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5">
    <div class="card shadow p-4">
        <h3 class="mb-3">Student Registration</h3>

        <?php foreach ($errors as $e): ?>
            <div class="alert alert-danger"><?= $e ?></div>
        <?php endforeach; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
            <a href="index.php" class="btn btn-primary">Go to Login</a>
        <?php else: ?>
            <form method="post">
                <div class="row mb-2">
                    <div class="col-md-6">
                        <input type="text" name="first_name" class="form-control" placeholder="First Name" required>
                    </div>
                    <div class="col-md-6">
                        <input type="text" name="last_name" class="form-control" placeholder="Last Name" required>
                    </div>
                </div>

                <div class="mb-2">
                    <input type="email" name="email" class="form-control" placeholder="Email" required>
                </div>

                <div class="mb-2">
                    <input type="text" name="contact" class="form-control" placeholder="Contact Number" required>
                </div>

                <div class="mb-2">
                    <input type="text" name="school_name" class="form-control" placeholder="School Name" required>
                </div>

                <div class="mb-2">
                    <input type="text" name="course" class="form-control" placeholder="Course" required>
                </div>

                <div class="mb-2">
                    <input type="text" name="year_level" class="form-control" placeholder="Year Level" required>
                </div>

                <div class="mb-2">
                    <input type="text" name="username" class="form-control" placeholder="Username" required>
                </div>
                <div class="mb-2">
                    <input type="password" name="password" class="form-control" placeholder="Password" required>
                </div>
                <div class="mb-3">
                    <input type="password" name="confirm_password" class="form-control" placeholder="Confirm Password" required>
                </div>

                <button type="submit" class="btn btn-success w-100">Register</button>
                <p class="text-center mt-3">Already have an account? <a href="index.php">Login here</a></p>
            </form>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
