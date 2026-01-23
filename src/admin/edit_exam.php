<?php
require_once __DIR__ . '/../middleware/auth.php';
requireRole('admin');

require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

$exam_id       = (int)($_POST['exam_id'] ?? 0);
$program_id    = isset($_POST['program_id']) ? (int) $_POST['program_id'] : 0;
$course_id     = isset($_POST['course_id']) ? (int) $_POST['course_id'] : 0;
$duration      = isset($_POST['duration']) ? (int) $_POST['duration'] : 0;
$total_marks   = isset($_POST['total_marks']) ? (int) $_POST['total_marks'] : 0;
$num_questions = isset($_POST['num_questions']) ? (int) $_POST['num_questions'] : 0;
$status        = trim($_POST['status'] ?? '');

$errors = [];

// Basic validation
if ($exam_id <= 0) $errors[] = 'Invalid exam.';
$allowedStatuses = ['draft','in_progress','closed'];
if ($status === '' || !in_array($status, $allowedStatuses, true)) $errors[] = 'Invalid status selected.';
if ($program_id <= 0) $errors[] = 'Please select a valid program.';
if ($course_id <= 0) $errors[] = 'Please select a valid course.';
if ($duration <= 0) $errors[] = 'Duration must be greater than 0.';
if ($total_marks < 0) $errors[] = 'Total marks must be 0 or greater.';
if ($num_questions <= 0) $errors[] = 'Number of questions must be at least 1.';

if (!empty($errors)) {
    $_SESSION['flash'] = implode(' ', $errors);
    header('Location: dashboard.php');
    exit;
}

/* ===========================
   Fetch exam (lock editing)
   =========================== */
try {
    $stmt = $pdo->prepare("SELECT id, status FROM exams WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $exam_id]);
    $exam = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$exam) {
        $_SESSION['flash'] = 'Exam not found.';
        header('Location: dashboard.php');
        exit;
    }

    // only allow edits on draft exams
    if ($exam['status'] !== 'draft') {
        $_SESSION['flash'] = 'Only draft exams can be edited.';
        header('Location: dashboard.php');
        exit;
    }
} catch (Throwable $e) {
    error_log('[edit_exam] Failed fetching exam: ' . $e->getMessage());
    $_SESSION['flash'] = 'Unable to validate exam. Try again later.';
    header('Location: dashboard.php');
    exit;
}

/* ===========================
   Validate program & course
   =========================== */
try {
    $pStmt = $pdo->prepare("SELECT id FROM programs WHERE id = :id LIMIT 1");
    $pStmt->execute([':id' => $program_id]);
    if (!$pStmt->fetch(PDO::FETCH_ASSOC)) {
        $_SESSION['flash'] = 'Selected program not found.';
        header('Location: dashboard.php');
        exit;
    }

    $cStmt = $pdo->prepare("SELECT id FROM courses WHERE id = :cid AND program_id = :pid LIMIT 1");
    $cStmt->execute([':cid' => $course_id, ':pid' => $program_id]);
    if (!$cStmt->fetch(PDO::FETCH_ASSOC)) {
        $_SESSION['flash'] = 'Selected course not found for the chosen program.';
        header('Location: dashboard.php');
        exit;
    }
} catch (Throwable $e) {
    error_log('[edit_exam] Program/course validation failed: ' . $e->getMessage());
    $_SESSION['flash'] = 'Unable to validate program or course. Try again later.';
    header('Location: dashboard.php');
    exit;
}

/* ===========================
   Validate marks vs questions
   =========================== */
try {
    $qMarksStmt = $pdo->prepare("
        SELECT COALESCE(SUM(marks), 0) AS total
        FROM questions
        WHERE exam_id = :exam_id
    ");
    $qMarksStmt->execute([':exam_id' => $exam_id]);
    $existingMarks = (int)$qMarksStmt->fetchColumn();

    if ($existingMarks > $total_marks) {
        $_SESSION['flash'] = "Total marks cannot be less than existing question marks ({$existingMarks}).";
        header('Location: dashboard.php');
        exit;
    }

    // Optional: ensure num_questions >= number of already created questions
    $qCountStmt = $pdo->prepare("SELECT COUNT(*) FROM questions WHERE exam_id = :exam_id");
    $qCountStmt->execute([':exam_id' => $exam_id]);
    $existingCount = (int)$qCountStmt->fetchColumn();

    if ($existingCount > $num_questions) {
        $_SESSION['flash'] = "Number of questions cannot be less than already created questions ({$existingCount}).";
        header('Location: dashboard.php');
        exit;
    }
} catch (Throwable $e) {
    error_log('[edit_exam] Validation failed: ' . $e->getMessage());
    $_SESSION['flash'] = 'Unable to validate question counts. Try again later.';
    header('Location: dashboard.php');
    exit;
}

/* ===========================
   Perform update
   =========================== */
try {
    $update = $pdo->prepare("
        UPDATE exams
        SET program_id = :program_id,
            course_id = :course_id,
            duration = :duration,
            total_marks = :total_marks,
            num_questions = :num_questions,
            status = :status
        WHERE id = :id
    ");

    $update->execute([
        ':program_id'    => $program_id,
        ':course_id'     => $course_id,
        ':duration'      => $duration,
        ':total_marks'   => $total_marks,
        ':num_questions' => $num_questions,
        ':status'        => $status,
        ':id'            => $exam_id
    ]);

    $_SESSION['flash'] = 'Exam updated successfully.';
    header('Location: dashboard.php');
    exit;
} catch (Throwable $e) {
    error_log('[edit_exam] Update failed: ' . $e->getMessage());
    $_SESSION['flash'] = 'Failed to update exam. Try again later.';
    header('Location: dashboard.php');
    exit;
}
