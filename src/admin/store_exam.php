<?php
// store_exam.php
session_start();

require_once __DIR__ . '/../middleware/auth.php';
requireRole('admin');
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

function post($k) { return isset($_POST[$k]) ? trim($_POST[$k]) : null; }

$title = post('title');
$description = post('description');
$duration = post('duration');
$total_marks = post('total_marks');
$status = post('status');
$program_id = post('program_id');
$course_id = post('course_id');

$errors = [];

// basic validation
if (empty($title)) $errors[] = "Exam title is required.";
if (empty($duration) || !is_numeric($duration) || (int)$duration < 1) $errors[] = "Duration must be at least 1 minute.";
if ($total_marks === null || !is_numeric($total_marks) || (int)$total_marks < 0) $errors[] = "Total marks must be 0 or greater.";
$allowedStatuses = ['draft','in_progress','closed'];
if (!in_array($status, $allowedStatuses, true)) $errors[] = "Invalid status selected.";

if (empty($program_id) || !is_numeric($program_id)) {
    $errors[] = "Please select a valid program.";
} else {
    // verify program exists
    try {
        $stmt = $pdo->prepare("SELECT id FROM programs WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => (int)$program_id]);
        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            $errors[] = "Selected program not found.";
        }
    } catch (Exception $e) {
        error_log("Program lookup failed: " . $e->getMessage());
        $errors[] = "Unable to validate program at this time.";
    }
}

if (empty($course_id) || !is_numeric($course_id)) {
    $errors[] = "Please select a valid course.";
} else {
    // verify course exists and belongs to program
    try {
        $stmt = $pdo->prepare("SELECT id FROM courses WHERE id = :cid AND program_id = :pid LIMIT 1");
        $stmt->execute([':cid' => (int)$course_id, ':pid' => (int)$program_id]);
        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            $errors[] = "Selected course not found for the chosen program.";
        }
    } catch (Exception $e) {
        error_log("Course lookup failed: " . $e->getMessage());
        $errors[] = "Unable to validate course at this time.";
    }
}

if (!empty($errors)) {
    $_SESSION['flash'] = implode(' ', $errors);
    header('Location: ' . (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php'));
    exit;
}

// insert
try {
    $sql = "INSERT INTO exams (title, description, duration, total_marks, status, program_id, course_id, created_at)
            VALUES (:title, :description, :duration, :total_marks, :status, :program_id, :course_id, NOW())";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':title' => $title,
        ':description' => $description,
        ':duration' => (int)$duration,
        ':total_marks' => (int)$total_marks,
        ':status' => $status,
        ':program_id' => (int)$program_id,
        ':course_id' => (int)$course_id
    ]);

    $_SESSION['flash'] = 'Exam created successfully.';
    header('Location: index.php');
    exit;
} catch (Exception $e) {
    error_log("Failed to create exam: " . $e->getMessage());
    $_SESSION['flash'] = 'Failed to create exam. Try again later.';
    header('Location: index.php');
    exit;
}
