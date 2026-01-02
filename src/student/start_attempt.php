<?php
// src/api/start_attempt.php
require_once __DIR__ . '../config/db.php';
require_once __DIR__ . '../middleware/auth.php';
header('Content-Type: application/json');

if ($_SESSION['user']['role'] !== 'student') {
    http_response_code(403); echo json_encode(['error'=>'Forbidden']); exit;
}

$student_id = $_SESSION['user']['id'];
$exam_id = intval($_POST['exam_id'] ?? 0);
if (!$exam_id) { http_response_code(400); echo json_encode(['error'=>'Missing exam']); exit; }

// fetch exam and ensure it is available
$stmt = $pdo->prepare("SELECT id, duration, status FROM exams WHERE id = ?");
$stmt->execute([$exam_id]);
$exam = $stmt->fetch();
if (!$exam || $exam['status'] !== 'in_progress') {
    http_response_code(400); echo json_encode(['error'=>'Exam not available']); exit;
}

// prevent repeated submitted attempts unless retake allowed (simple check)
$existing = $pdo->prepare("SELECT id, status FROM attempts WHERE exam_id = ? AND student_id = ? ORDER BY id DESC LIMIT 1");
$existing->execute([$exam_id, $student_id]);
$last = $existing->fetch();
if ($last && in_array($last['status'], ['submitted','auto_submitted'])) {
    http_response_code(403);
    echo json_encode(['error'=>'Exam already attempted']);
    exit;
}

// create attempt
$started_at = (new DateTime())->format('Y-m-d H:i:s');
$duration_minutes = (int)$exam['duration'];
$ins = $pdo->prepare("INSERT INTO attempts (exam_id, student_id, started_at, status, duration_minutes) VALUES (?, ?, ?, 'in_progress', ?)");
$ins->execute([$exam_id, $student_id, $started_at, $duration_minutes]);
$attempt_id = $pdo->lastInsertId();

// return attempt info (ISO 8601 start time)
echo json_encode([
    'attempt_id' => (int)$attempt_id,
    'started_at' => (new DateTime($started_at))->format(DateTime::ATOM),
    'duration_minutes' => $duration_minutes
]);
exit;
