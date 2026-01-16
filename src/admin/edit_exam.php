<?php
require_once __DIR__ . '/../middleware/auth.php';
requireRole('admin');

require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

$exam_id = (int)($_POST['exam_id'] ?? 0);
$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$duration = (int)($_POST['duration'] ?? 0);
$total_marks = (int)($_POST['total_marks'] ?? 0);

$errors = [];

if ($exam_id <= 0) {
    $errors[] = 'Invalid exam.';
}

if ($title === '') {
    $errors[] = 'Exam title is required.';
}

if ($duration <= 0) {
    $errors[] = 'Duration must be greater than 0.';
}

if ($total_marks <= 0) {
    $errors[] = 'Total marks must be greater than 0.';
}

/* ===========================
   Fetch exam (lock editing)
=========================== */
$stmt = $pdo->prepare("
    SELECT id, status
    FROM exams
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$exam_id]);
$exam = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$exam) {
    $errors[] = 'Exam not found.';
} elseif ($exam['status'] !== 'draft') {
    $errors[] = 'Only draft exams can be edited.';
}

/* ===========================
   Validate marks vs questions
=========================== */
$qMarksStmt = $pdo->prepare("
    SELECT COALESCE(SUM(marks), 0)
    FROM questions
    WHERE exam_id = ?
");
$qMarksStmt->execute([$exam_id]);
$existingMarks = (int)$qMarksStmt->fetchColumn();

if ($existingMarks > $total_marks) {
    $errors[] = "Total marks cannot be less than existing question marks ($existingMarks).";
}

/* ===========================
   Handle errors
=========================== */
if (!empty($errors)) {
    $_SESSION['flash'] = implode(' ', $errors);
    header('Location: dashboard.php');
    exit;
}

/* ===========================
   Update exam
=========================== */
$update = $pdo->prepare("
    UPDATE exams
    SET title = ?, description = ?, duration = ?, total_marks = ?
    WHERE id = ?
");
$update->execute([
    $title,
    $description,
    $duration,
    $total_marks,
    $exam_id
]);

$_SESSION['flash'] = 'Exam updated successfully.';
header('Location: dashboard.php');
exit;
