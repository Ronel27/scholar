<?php
require_once 'config/Database.php';
$db = (new Database())->connect();
try {
    $username = 'admin';
    $password = password_hash('admin123', PASSWORD_DEFAULT);
    $role = 'admin';

    $stmt = $db->prepare('SELECT * FROM users WHERE username = :u');
    $stmt->execute([':u'=>$username]);
    if ($stmt->rowCount() === 0) {
        $i = $db->prepare('INSERT INTO users (username, password, role) VALUES (:u,:p,:r)');
        $i->execute([':u'=>$username, ':p'=>$password, ':r'=>$role]);
        echo 'Admin created. Username: admin / Password: admin123';
    } else {
        echo 'Admin already exists.';
    }
} catch (PDOException $e) {
    echo 'Error: ' . $e->getMessage();
}
?>