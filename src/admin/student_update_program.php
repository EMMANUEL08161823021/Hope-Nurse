<?php
// src/admin/student_update_program.php
require_once '../middleware/auth.php';
requireRole('admin');
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: students.php');
    exit;
}

$studentId = (int)($_POST['student_id'] ?? 0);
$program = trim((string)($_POST['program'] ?? ''));

// validate
if ($studentId <= 0 || $program === '') {
    $_SESSION['flash'] = 'Invalid request.';
    header('Location: students.php');
    exit;
}

// ensure program exists (optional safety)
$stmt = $pdo->prepare("SELECT id FROM programs WHERE name = :name LIMIT 1");
$stmt->execute([':name' => $program]);
$prog = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$prog) {
    $_SESSION['flash'] = 'Selected program does not exist.';
    header('Location: students.php');
    exit;
}

// update user's program
$update = $pdo->prepare("UPDATE users SET program = :prog WHERE id = :id");
try {
    $update->execute([':prog' => $program, ':id' => $studentId]);
    $_SESSION['flash'] = 'Student program updated.';
} catch (Exception $e) {
    error_log('[student_update_program] ' . $e->getMessage());
    $_SESSION['flash'] = 'Failed to update student program. Try again later.';
}

header('Location: students.php');
exit;
