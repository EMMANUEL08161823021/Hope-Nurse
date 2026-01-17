<?php
// src/auth/register.php  (acts as login handler in your app)
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

// lookup user by email
$stmt = $pdo->prepare("SELECT id, password, role, status, full_name, email FROM users WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || !password_verify($password, $user['password'])) {
    // do not reveal which part failed
    $_SESSION['login_error'] = 'Incorrect email or password.';
    header('Location: login.php');
    exit;
}

// check blocked status
if (isset($user['status']) && $user['status'] === 'blocked') {
    $_SESSION['login_error'] = 'Your account has been blocked. Contact an administrator.';
    header('Location: login.php');
    exit;
}

// Good login: regenerate session id and store minimal info
session_regenerate_id(true);

$_SESSION['user'] = [
    'id' => (int)$user['id'],
    'role' => $user['role'],
    'full_name' => $user['full_name'] ?? '',
    'email' => $user['email'] ?? '',
    'status' => $user['status'] ?? 'active'
];

// Redirect by role (adjust paths if your dashboard paths differ)
if ($user['role'] === 'admin') {
    header("Location: ../admin/dashboard.php");
} else {
    header("Location: ../student/dashboard.php");
}
exit;
