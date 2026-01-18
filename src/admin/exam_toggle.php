<?php
// src/admin/exam_toggle.php
require_once __DIR__ . '/../middleware/auth.php';
requireRole('admin');
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Method not allowed');
}

$exam_id = (int)($_POST['exam_id'] ?? 0);
$newStatus = trim((string)($_POST['status'] ?? ''));

// allowed statuses
$allowed = ['draft', 'in_progress', 'closed'];

if ($exam_id <= 0 || !in_array($newStatus, $allowed, true)) {
    $_SESSION['flash_error'] = 'Invalid request.';
    header('Location: exams_view.php?id=' . $exam_id);
    exit;
}

/* Load exam */
$stm = $pdo->prepare("SELECT id, status, total_marks FROM exams WHERE id = ? LIMIT 1");
$stm->execute([$exam_id]);
$exam = $stm->fetch(PDO::FETCH_ASSOC);

if (!$exam) {
    $_SESSION['flash_error'] = 'Exam not found.';
    header('Location: exams_view.php?id=' . $exam_id);
    exit;
}

$current = $exam['status'];


/*
 | Business rules:
 | - draft -> in_progress:
 |     * exam.total_marks must be set (> 0)
 |     * there must be at least one question
 |     * sum(question.marks) must equal exam.total_marks
 | - in_progress -> closed: allowed
 | - closed -> draft: only allowed if no attempts exist (cannot reopen if students attempted)
 | - other transitions that keep same status are treated as no-op
*/

try {
    // quick helpers
    $countQuestionsStmt = $pdo->prepare("SELECT COUNT(*) FROM questions WHERE exam_id = ?");
    $sumMarksStmt = $pdo->prepare("SELECT COALESCE(SUM(marks),0) FROM questions WHERE exam_id = ?");
    $attemptsCountStmt = $pdo->prepare("SELECT COUNT(*) FROM attempts WHERE exam_id = ?");

    if ($current === $newStatus) {
        $_SESSION['flash'] = 'Exam already in "' . htmlspecialchars($current) . '" status.';
        header('Location: exams_view.php?id=' . $exam_id);
        exit;
    }

    if ($current === 'draft' && $newStatus === 'in_progress') {
        // validate total marks
        $totalMarks = (int)($exam['total_marks'] ?? 0);
        if ($totalMarks <= 0) {
            throw new Exception('Total marks must be set and greater than 0 before starting the exam.');
        }

        // questions exist?
        $countQuestionsStmt->execute([$exam_id]);
        $qCount = (int)$countQuestionsStmt->fetchColumn();
        if ($qCount <= 0) throw new Exception('Add questions before starting the exam.');

        // marks sum check
        $sumMarksStmt->execute([$exam_id]);
        $sumMarks = (int)$sumMarksStmt->fetchColumn();
        if ($sumMarks !== $totalMarks) {
            throw new Exception("Sum of question marks ({$sumMarks}) does not equal exam total marks ({$totalMarks}).");
        }

        // allowed: update to in_progress
    } elseif ($current === 'in_progress' && $newStatus === 'closed') {
        // allowed: close exam
    } elseif ($current === 'closed' && $newStatus === 'draft') {
        // only allow reopening to draft if no attempts exist
        $attemptsCountStmt->execute([$exam_id]);
        $attempts = (int)$attemptsCountStmt->fetchColumn();
        if ($attempts > 0) {
            throw new Exception('Cannot reopen exam to draft: students have attempted this exam.');
        }
    } elseif ($current === 'draft' && $newStatus === 'closed') {
        // Disallow closing directly from draft (must start first)
        throw new Exception('Cannot close an exam that has not been started. Start it (In Progress) first.');
    } elseif ($current === 'in_progress' && $newStatus === 'draft') {
        // Prevent reverting an in_progress exam to draft while students might be taking it
        $attemptsCountStmt->execute([$exam_id]);
        $attempts = (int)$attemptsCountStmt->fetchColumn();
        if ($attempts > 0) {
            throw new Exception('Cannot revert to draft: students have attempted this exam.');
        }
        // If zero attempts, we allow in_progress -> draft (rare case)
    } else {
        // Any other transitions not explicitly allowed are treated as invalid
        // But we allow closed -> in_progress only if no attempts exist (edge case)
        if ($current === 'closed' && $newStatus === 'in_progress') {
            $attemptsCountStmt->execute([$exam_id]);
            $attempts = (int)$attemptsCountStmt->fetchColumn();
            if ($attempts > 0) throw new Exception('Cannot re-open exam that already has attempts.');
            // else allow re-open
        } else {
            throw new Exception('This status transition is not allowed.');
        }
    }

    // perform update
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
