<?php
// src/admin/exam_delete.php
session_start();
require_once '../middleware/auth.php';
requireRole('admin');
require_once '../config/db.php';

$back = $_SERVER['HTTP_REFERER'] ?? 'exam.php';

// Accept id from GET (anchor) or POST (form)
$exam_id = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $exam_id = (int)($_POST['exam_id'] ?? 0);
} else {
    $exam_id = (int)($_GET['id'] ?? 0);
}

if ($exam_id <= 0) {
    $_SESSION['flash'] = 'Invalid exam ID.';
    header('Location: ' . $back);
    exit;
}

try {
    // Fetch exam
    $stmt = $pdo->prepare("SELECT id, title, status FROM exams WHERE id = ? LIMIT 1");
    $stmt->execute([$exam_id]);
    $exam = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$exam) {
        $_SESSION['flash'] = 'Exam not found.';
        header('Location: ' . $back);
        exit;
    }

    // Do not allow deleting an active exam
    if ($exam['status'] === 'in_progress') {
        $_SESSION['flash'] = 'Cannot delete an exam that is currently in progress.';
        header('Location: ' . $back);
        exit;
    }

    // Do not allow deleting if there are attempts
    $chk = $pdo->prepare("SELECT COUNT(*) FROM attempts WHERE exam_id = ?");
    $chk->execute([$exam_id]);
    $attemptCount = (int) $chk->fetchColumn();
    if ($attemptCount > 0) {
        $_SESSION['flash'] = 'Cannot delete exam. Students have already attempted it.';
        header('Location: ' . $back);
        exit;
    }

    // Delete related records in a transaction
    $pdo->beginTransaction();

    // Delete options for questions belonging to this exam
    $pdo->prepare("
        DELETE qo
        FROM options qo
        JOIN questions q ON qo.question_id = q.id
        WHERE q.exam_id = ?
    ")->execute([$exam_id]);

    // Delete questions
    $pdo->prepare("DELETE FROM questions WHERE exam_id = ?")->execute([$exam_id]);

    // Delete exam
    $pdo->prepare("DELETE FROM exams WHERE id = ?")->execute([$exam_id]);

    $pdo->commit();

    $_SESSION['flash'] = 'Exam "' . $exam['title'] . '" deleted successfully.';
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('exam_delete error: ' . $e->getMessage());
    $_SESSION['flash'] = 'An error occurred while deleting the exam.';
}

header('Location: ' . $back);
exit;
