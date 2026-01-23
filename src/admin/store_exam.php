<?php
// store_exam.php
session_start();

// require admin role + DB (adjust paths if your project layout differs)
require_once __DIR__ . '/../middleware/auth.php';
requireRole('admin');
require_once __DIR__ . '/../config/db.php';

// Development helper: uncomment to see all POST data during debugging
// var_dump($_POST); exit;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

function post($k) { return isset($_POST[$k]) ? trim($_POST[$k]) : null; }

$program_id  = post('program_id');
$course_id   = post('course_id');
$duration    = post('duration');
$total_marks = post('total_marks');
$status      = post('status');
$num_questions = post('num_questions');

$errors = [];

// basic server-side validation
if (empty($program_id) || !is_numeric($program_id)) {
    $errors[] = "Please select a valid program.";
}
if (empty($course_id) || !is_numeric($course_id)) {
    $errors[] = "Please select a valid course.";
}
if (empty($duration) || !is_numeric($duration) || (int)$duration < 1) {
    $errors[] = "Duration must be at least 1 minute.";
}
if ($total_marks === null || $total_marks === '' || !is_numeric($total_marks) || (int)$total_marks < 0) {
    $errors[] = "Total marks must be 0 or greater.";
}
$allowedStatuses = ['draft','in_progress','closed'];
if (!in_array($status, $allowedStatuses, true)) {
    $errors[] = "Invalid status selected.";
}

if (empty($num_questions) || !is_numeric($num_questions) || (int)$num_questions < 1) {
    $errors[] = "Number of questions must be at least 1.";
}

// ensure admin is logged in and we have an id for FK
if (empty($_SESSION['user']['id']) || !is_numeric($_SESSION['user']['id'])) {
    $errors[] = "Unable to identify the current user. Please login and try again.";
}

if (!empty($errors)) {
    // Save friendly message and redirect back
    $_SESSION['flash'] = implode(' ', $errors);
    header('Location: index.php');
    exit;
}

// cast to ints
$program_id  = (int)$program_id;
$course_id   = (int)$course_id;
$duration    = (int)$duration;
$num_questions = (int)$num_questions;
$total_marks = (int)$total_marks;
$created_by  = (int)$_SESSION['user']['id'];

try {
    // Verify program exists
    $stmt = $pdo->prepare("SELECT id FROM programs WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $program_id]);
    if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
        throw new Exception('Selected program not found.');
    }

    // Verify course exists and belongs to program
    $stmt = $pdo->prepare("SELECT id FROM courses WHERE id = :cid AND program_id = :pid LIMIT 1");
    $stmt->execute([':cid' => $course_id, ':pid' => $program_id]);
    if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
        throw new Exception('Selected course not found for the chosen program.');
    }

    // Verify creator user exists (defensive)
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $created_by]);
    if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
        throw new Exception('Current user not found in users table.');
    }

    // Insert exam (no title/description)
    $sql = "INSERT INTO exams (
            program_id,
            course_id,
            duration,
            total_marks,
            num_questions,
            status,
            created_by,
            created_at
        )
        VALUES (
            :program_id,
            :course_id,
            :duration,
            :total_marks,
            :num_questions,
            :status,
            :created_by,
            NOW()
        )";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':program_id'    => $program_id,
        ':course_id'     => $course_id,
        ':duration'      => $duration,
        ':total_marks'   => $total_marks,
        ':num_questions' => $num_questions,
        ':status'        => $status,
        ':created_by'    => $created_by
    ]);

    $_SESSION['flash'] = 'Exam created successfully.';
    header('Location: dashboard.php');
    exit;

} catch (Throwable $e) {
    // Log the detailed error for debugging; show a friendly message to user
    error_log('[store_exam] Create exam failed: ' . $e->getMessage() . ' | ' . json_encode([
        'program_id' => $program_id,
        'course_id' => $course_id,
        'duration' => $duration,
        'total_marks' => $total_marks,
        'num_questions' => $num_questions,
        'created_by' => $created_by
    ]));

    // If you're actively debugging, you can temporarily uncomment the following to see the real error:
    // die('<pre>' . htmlspecialchars($e->getMessage()) . '</pre>');

    $_SESSION['flash'] = 'Failed to create exam. Try again later.';
    header('Location: dashboard.php');
    exit;
}
