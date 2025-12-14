<?php
if(session_status() == PHP_SESSION_NONE) session_start();

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf_token'];
}

function csrf_input_field() {
    $t = htmlspecialchars(csrf_token(), ENT_QUOTES);
    return '<input type="hidden" name="csrf_token" value="' . $t . '">';
}

function csrf_check() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
            die('Invalid CSRF token - operation not allowed.');
        }
    }
}
?>
