<?php
require_once '../middleware/auth.php';
requireRole('admin');
require_once '../db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Invalid request');
}

$title = trim($_POST['title']);
$description = trim($_POST['description']);
$duration = (int) $_POST['duration'];
$total_marks = (int) $_POST['total_marks'];
$status = $_POST['status'];
$admin_id = $_SESSION['user']['id'];

// Validation
if ($title === '' || $duration <= 0 || $total_marks < 0) {
    die('Invalid input');
}

$stmt = $pdo->prepare("
    INSERT INTO exams (created_by, title, description, duration, total_marks, status)
    VALUES (?, ?, ?, ?, ?, ?)
");

$stmt->execute([
    $admin_id,
    $title,
    $description,
    $duration,
    $total_marks,
    $status
]);

header('Location: exams.php');
exit;
