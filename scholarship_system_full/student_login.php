<?php
session_start();
require_once 'config/Database.php';
$db = (new Database())->connect();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    $stmt = $db->prepare("SELECT * FROM users WHERE username=? AND role='student'");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && md5($password) === $user['password']) {
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['role'] = 'student';
        header('Location: student/dashboard.php');
        exit;
    } else {
        $error = "Invalid username or password";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5">
    <div class="card shadow p-4 col-md-4 mx-auto">
        <h3 class="mb-3 text-center">Student Login</h3>

        <?php if($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="mb-2">
                <input type="text" name="username" class="form-control" placeholder="Username" required>
            </div>
            <div class="mb-3">
                <input type="password" name="password" class="form-control" placeholder="Password" required>
            </div>
            <button type="submit" class="btn btn-success w-100">Login</button>
            <a href="index.php" class="btn btn-secondary w-100 mt-2">Back</a>
        </form>
    </div>
</div>

</body>
</html>
