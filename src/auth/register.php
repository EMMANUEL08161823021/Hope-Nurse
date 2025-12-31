<?php
session_start();
require_once '../db.php';

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';



if (!$email || !$password) {
    die('Invalid login attempt');
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password'])) {
    die('Incorrect email or password');
}

// Store only necessary data in session
$_SESSION['user'] = [
    'id' => $user['id'],
    'role' => $user['role'],
    'name' => $user['full_name']
];

// Redirect by role
if ($user['role'] === 'admin') {
    header("Location: ../admin/dashboard.php");
} else {
    header("Location: ../student/dashboard.php");
}
exit;
