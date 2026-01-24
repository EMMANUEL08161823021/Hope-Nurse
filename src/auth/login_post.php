<?php
// src/auth/login_post.php
session_start();
require_once '../config/db.php';

// normalize and validate
$email = trim((string)($_POST['email'] ?? ''));
$password = $_POST['password'] ?? '';

if ($email === '' || $password === '') {
    $_SESSION['login_error'] = 'Email and password are required.';
    header('Location: login.php');
    exit;
}

$stmt = $pdo->prepare("SELECT id, password, role, status, full_name, email FROM users WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || !password_verify($password, $user['password'])) {
    $_SESSION['login_error'] = 'Incorrect email or password.';
    header('Location: login.php');
    exit;
}

if (isset($user['status']) && $user['status'] === 'blocked') {
    $_SESSION['login_error'] = 'Your account has been blocked. Contact an administrator.';
    header('Location: login.php');
    exit;
}

session_regenerate_id(true);
$_SESSION['user'] = [
    'id' => (int)$user['id'],
    'role' => $user['role'],
    'full_name' => $user['full_name'] ?? '',
    'email' => $user['email'] ?? '',
    'status' => $user['status'] ?? 'active'
];

if ($user['role'] === 'admin') {
    header("Location: ../admin/dashboard.php");
} else {
    header("Location: ../student/dashboard.php");
}
exit;
