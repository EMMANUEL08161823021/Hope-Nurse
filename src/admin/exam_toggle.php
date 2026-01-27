<?php
require_once __DIR__ . '/../middleware/auth.php';
requireRole('admin');
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Method not allowed');
}

$exam_id = (int)($_POST['exam_id'] ?? 0);
$newStatus = trim((string)($_POST['status'] ?? ''));

$allowed = ['draft', 'in_progress', 'closed'];

if ($exam_id <= 0 || !in_array($newStatus, $allowed, true)) {
    $_SESSION['flash_error'] = 'Invalid request.';
    header('Location: exams_view.php?id=' . $exam_id);
    exit;
}

$stm = $pdo->prepare("SELECT id, status, total_marks FROM exams WHERE id = ? LIMIT 1");
$stm->execute([$exam_id]);
$exam = $stm->fetch(PDO::FETCH_ASSOC);

if (!$exam) {
    $_SESSION['flash_error'] = 'Exam not found.';
    header('Location: exams_view.php?id=' . $exam_id);
    exit;
}

$current = $exam['status'];


try {
    $countQuestionsStmt = $pdo->prepare("SELECT COUNT(*) FROM questions WHERE exam_id = ?");
    $sumMarksStmt = $pdo->prepare("SELECT COALESCE(SUM(marks),0) FROM questions WHERE exam_id = ?");
    $attemptsCountStmt = $pdo->prepare("SELECT COUNT(*) FROM attempts WHERE exam_id = ?");

    if ($current === $newStatus) {
        $_SESSION['flash'] = 'Exam already in "' . htmlspecialchars($current) . '" status.';
        header('Location: exams_view.php?id=' . $exam_id);
        exit;
    }

    if ($current === 'draft' && $newStatus === 'in_progress') {
        $totalMarks = (int)($exam['total_marks'] ?? 0);
        if ($totalMarks <= 0) {
            throw new Exception('Total marks must be set and greater than 0 before starting the exam.');
        }

        $countQuestionsStmt->execute([$exam_id]);
        $qCount = (int)$countQuestionsStmt->fetchColumn();
        if ($qCount <= 0) throw new Exception('Add questions before starting the exam.');

        $sumMarksStmt->execute([$exam_id]);
        $sumMarks = (int)$sumMarksStmt->fetchColumn();
        if ($sumMarks !== $totalMarks) {
            throw new Exception("Sum of question marks ({$sumMarks}) does not equal exam total marks ({$totalMarks}).");
        }

    } elseif ($current === 'in_progress' && $newStatus === 'closed') {
    } elseif ($current === 'closed' && $newStatus === 'draft') {
        $attemptsCountStmt->execute([$exam_id]);
        $attempts = (int)$attemptsCountStmt->fetchColumn();
        if ($attempts > 0) {
            throw new Exception('Cannot reopen exam to draft: students have attempted this exam.');
        }
    } elseif ($current === 'draft' && $newStatus === 'closed') {
        throw new Exception('Cannot close an exam that has not been started. Start it (In Progress) first.');
    } elseif ($current === 'in_progress' && $newStatus === 'draft') {
        $attemptsCountStmt->execute([$exam_id]);
        $attempts = (int)$attemptsCountStmt->fetchColumn();
        if ($attempts > 0) {
            throw new Exception('Cannot revert to draft: students have attempted this exam.');
        }
    } else {
        if ($current === 'closed' && $newStatus === 'in_progress') {
            $attemptsCountStmt->execute([$exam_id]);
            $attempts = (int)$attemptsCountStmt->fetchColumn();
            if ($attempts > 0) throw new Exception('Cannot re-open exam that already has attempts.');
        } else {
            throw new Exception('This status transition is not allowed.');
        }
    }

    $upd = $pdo->prepare("UPDATE exams SET status = ? WHERE id = ?");
    $upd->execute([$newStatus, $exam_id]);

    $_SESSION['flash'] = "Exam status changed to " . htmlspecialchars(ucfirst($newStatus)) . ".";
    header('Location: exams_view.php?id=' . $exam_id);
    exit;

} catch (Exception $e) {
    $_SESSION['flash_error'] = $e->getMessage();
    header('Location: exams_view.php?id=' . $exam_id);
    exit;
}
