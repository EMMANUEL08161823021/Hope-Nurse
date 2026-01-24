<?php
// src/auth/register_post.php
session_start();
require_once '../config/db.php';

$errors = [];
$allowedPrograms = ['Hope Prep', 'DIY', 'Cradle'];

$full_name = trim($_POST['full_name'] ?? '');
$email     = trim($_POST['email'] ?? '');
$country   = trim($_POST['country'] ?? '');
$program   = $_POST['program'] ?? 'Hope Prep';
$password  = $_POST['password'] ?? '';
$confirm   = $_POST['confirm_password'] ?? '';

$_SESSION['register_old'] = compact('full_name', 'email', 'country', 'program');

/* ---------- VALIDATION ---------- */

if ($full_name === '') {
    $errors[] = 'Full name is required.';
}

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'A valid email is required.';
}

if (!in_array($program, $allowedPrograms, true)) {
    $errors[] = 'Invalid program selected.';
}

if (strlen($password) < 8) {
    $errors[] = 'Password must be at least 8 characters.';
}

if ($password !== $confirm) {
    $errors[] = 'Passwords do not match.';
}

/* ---------- DUPLICATE EMAIL ---------- */
if (!$errors) {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1");
    $stmt->execute([$email]);

    if ($stmt->fetch()) {
        $errors[] = 'Email already registered.';
    }
}

/* ---------- HANDLE ERRORS ---------- */
if ($errors) {
    $_SESSION['register_errors'] = $errors;
    header('Location: register.php');
    exit;
}

/* ---------- INSERT USER ---------- */
$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("
    INSERT INTO users (full_name, email, password, role, status, country, program, created_at)
    VALUES (?, ?, ?, 'student', 'active', ?, ?, NOW())
");

$stmt->execute([
    $full_name,
    $email,
    $hash,
    $country ?: null,
    $program
]);

$_SESSION['flash'] = 'Account created successfully. You can now log in.';
unset($_SESSION['register_old']);

header('Location: login.php');
exit;
