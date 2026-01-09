<?php
session_start();
require_once '../middleware/auth.php';
requireRole('admin');
require_once '../config/db.php';

$back = $_SERVER['HTTP_REFERER'] ?? 'exams.php';

$exam_id = (int)($_GET['id'] ?? 0);
$action  = $_GET['action'] ?? '';

if ($exam_id <= 0 || !in_array($action, ['start', 'close'], true)) {
    $_SESSION['flash'] = 'Invalid exam action.';
    header("Location: $back");
    exit;
}

// Fetch exam
$stmt = $pdo->prepare("SELECT id, title, status FROM exams WHERE id = ?");
$stmt->execute([$exam_id]);
$exam = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$exam) {
    $_SESSION['flash'] = 'Exam not found.';
    header("Location: $back");
    exit;
}

// Decide new status (simple rules)
if ($action === 'start') {
    $newStatus = 'in_progress';
} else { // close
    $newStatus = 'closed';
}

// Prevent pointless update
if ($exam['status'] === $newStatus) {
    $_SESSION['flash'] = 'Exam is already ' . str_replace('_', ' ', $newStatus) . '.';
    header("Location: $back");
    exit;
}

// Update
$upd = $pdo->prepare("UPDATE exams SET status = ? WHERE id = ?");
$upd->execute([$newStatus, $exam_id]);

$_SESSION['flash'] = 'Exam "' . $exam['title'] . '" is now ' . str_replace('_', ' ', $newStatus) . '.';

header("Location: $back");
exit;
