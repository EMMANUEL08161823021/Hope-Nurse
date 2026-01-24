<?php
// src/api/start_attempt.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/auth.php';

header('Content-Type: application/json');

if (empty($_SESSION['user']) || $_SESSION['user']['role'] !== 'student') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

$student_id = (int)($_SESSION['user']['id'] ?? 0);
$exam_id = (int)($_POST['exam_id'] ?? 0);

if ($student_id <= 0 || $exam_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid student or exam id']);
    exit;
}

try {
    // fetch exam and ensure it is available
    $stmt = $pdo->prepare("SELECT id, duration, status FROM exams WHERE id = ? LIMIT 1");
    $stmt->execute([$exam_id]);
    $exam = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$exam || ($exam['status'] ?? '') !== 'in_progress') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Exam not available']);
        exit;
    }

    // normalize exam duration -> ensure integer and at least 1
    $examDuration = isset($exam['duration']) ? (int)$exam['duration'] : 0;
    if ($examDuration <= 0) {
        error_log("[start_attempt] Invalid exam.duration for exam_id={$exam_id}; falling back to 1 minute.");
        $examDuration = 1;
    }

    // fetch the student's last attempt for this exam
    $existing = $pdo->prepare("SELECT id, status, started_at, duration_minutes FROM attempts WHERE exam_id = ? AND student_id = ? ORDER BY id DESC LIMIT 1");
    $existing->execute([$exam_id, $student_id]);
    $last = $existing->fetch(PDO::FETCH_ASSOC);

    if ($last) {
        // if there's an in_progress attempt, return it so student can resume
        if (($last['status'] ?? '') === 'in_progress') {
            $startedAt = new DateTime($last['started_at']);
            echo json_encode([
                'success' => true,
                'attempt_id' => (int)$last['id'],
                'started_at' => $startedAt->format(DateTime::ATOM),
                'duration_minutes' => max(1, (int)($last['duration_minutes'] ?? $examDuration)),
                'resumed' => true
            ]);
            exit;
        }

        // block if last attempt status indicates submitted/finished
        if (in_array($last['status'], ['submitted','auto_submitted'], true)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Exam already attempted']);
            exit;
        }
        // otherwise (e.g., 'draft' or other statuses), proceed to create a new attempt
    }

    // create a new attempt (use transaction to be safe)
    $started_at_obj = new DateTime();
    $started_at = $started_at_obj->format('Y-m-d H:i:s');
    $duration_minutes = $examDuration;

    $pdo->beginTransaction();
    $ins = $pdo->prepare("
        INSERT INTO attempts (exam_id, student_id, started_at, status, duration_minutes)
        VALUES (:exam_id, :student_id, :started_at, 'in_progress', :duration_minutes)
    ");
    $ins->execute([
        ':exam_id' => $exam_id,
        ':student_id' => $student_id,
        ':started_at' => $started_at,
        ':duration_minutes' => $duration_minutes
    ]);
    $attempt_id = (int)$pdo->lastInsertId();
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'attempt_id' => $attempt_id,
        'started_at' => $started_at_obj->format(DateTime::ATOM),
        'duration_minutes' => $duration_minutes,
        'resumed' => false
    ]);
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[start_attempt] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error starting attempt']);
    exit;
}
