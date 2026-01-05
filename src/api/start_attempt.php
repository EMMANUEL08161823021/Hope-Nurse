<?php
// src/api/start_attempt.php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/auth.php';

// Determine whether caller expects JSON (AJAX / fetch)
function wantsJson(): bool {
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $xhr   = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    return (stripos($accept, 'application/json') !== false) ||
           (strtolower($xhr) === 'xmlhttprequest');
}

// Ensure logged in student
if (empty($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'student') {
    if (wantsJson()) {
        header('Content-Type: application/json', true, 403);
        echo json_encode(['error' => 'Forbidden']);
    } else {
        http_response_code(403);
        echo 'Forbidden';
    }
    exit;
}

$student_id = (int) ($_SESSION['user']['id'] ?? 0);

// Read exam_id (support form-POST or JSON body)
$exam_id = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // If Content-Type is application/json, read raw body
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $body = json_decode(file_get_contents('php://input'), true);
        if (is_array($body) && !empty($body['exam_id'])) {
            $exam_id = (int)$body['exam_id'];
        }
    } else {
        // normal form POST
        $exam_id = (int) ($_POST['exam_id'] ?? 0);
    }
} else {
    // fallback GET (not recommended, but supported)
    $exam_id = (int) ($_GET['exam_id'] ?? 0);
}

if ($exam_id <= 0) {
    if (wantsJson()) {
        header('Content-Type: application/json', true, 400);
        echo json_encode(['error' => 'Missing or invalid exam_id']);
    } else {
        header('Location: ../student/exams.php');
    }
    exit;
}

try {
    // Fetch exam and ensure it's available
    $stmt = $pdo->prepare("SELECT id, status FROM exams WHERE id = ? LIMIT 1");
    $stmt->execute([$exam_id]);
    $exam = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$exam || ($exam['status'] !== 'in_progress')) {
        if (wantsJson()) {
            header('Content-Type: application/json', true, 400);
            echo json_encode(['error' => 'Exam not available']);
        } else {
            // redirect back to exams list with a message (you can enhance with flash)
            header('Location: ../student/exams.php');
        }
        exit;
    }

    // Look for an existing attempt for this student+exam
    $check = $pdo->prepare("
        SELECT id, status, started_at, duration_minutes
        FROM attempts
        WHERE exam_id = ? AND student_id = ?
        ORDER BY id DESC
        LIMIT 1
    ");
    $check->execute([$exam_id, $student_id]);
    $existing = $check->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        // If already in progress: resume
        if ($existing['status'] === 'in_progress') {
            if (wantsJson()) {
                header('Content-Type: application/json', true, 200);
                echo json_encode([
                    'attempt_id' => (int)$existing['id'],
                    'message' => 'resumed'
                ]);
            } else {
                header('Location: ../student/take_exam.php?attempt_id=' . (int)$existing['id']);
            }
            exit;
        }

        // If already submitted or auto_submitted: block (unless you allow retakes)
        if (in_array($existing['status'], ['submitted', 'auto_submitted'], true)) {
            if (wantsJson()) {
                header('Content-Type: application/json', true, 403);
                echo json_encode(['error' => 'You have already completed this exam.']);
            } else {
                // could redirect with a flash message
                header('Location: ../student/exams.php');
            }
            exit;
        }
    }

    // No active attempt — compute duration based on question count
    $q = $pdo->prepare("SELECT COUNT(*) FROM questions WHERE exam_id = ?");
    $q->execute([$exam_id]);
    $questionCount = (int)$q->fetchColumn();

    // RULE: minutes per question (adjust multiplier to your policy)
    $minutesPerQuestion = 2; // example: 2 minutes per question
    $duration_minutes = max(1, $questionCount * $minutesPerQuestion);

    // Create new attempt — safe insert with try/catch to handle possible race (unique constraint)
    $started_at = (new DateTime())->format('Y-m-d H:i:s');

    try {
        $ins = $pdo->prepare("
            INSERT INTO attempts (exam_id, student_id, started_at, status, duration_minutes)
            VALUES (?, ?, ?, 'in_progress', ?)
        ");
        $ins->execute([$exam_id, $student_id, $started_at, $duration_minutes]);
        $attempt_id = (int)$pdo->lastInsertId();

    } catch (PDOException $e) {
        // Handle duplicate-key race: if another request created the attempt concurrently
        if ($e->getCode() === '23000') { // integrity constraint violation
            // fetch the existing attempt and resume
            $check->execute([$exam_id, $student_id]);
            $existing = $check->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                $attempt_id = (int)$existing['id'];
            } else {
                // unexpected: rethrow
                throw $e;
            }
        } else {
            throw $e;
        }
    }

    // Respond depending on caller
    if (wantsJson()) {
        header('Content-Type: application/json', true, 200);
        echo json_encode([
            'attempt_id' => $attempt_id,
            'started_at' => (new DateTime($started_at))->format(DateTime::ATOM),
            'duration_minutes' => (int)$duration_minutes,
        ]);
    } else {
        // Normal form POST: redirect to take_exam
        header('Location: ../student/take_exam.php?attempt_id=' . $attempt_id);
    }
    exit;

} catch (PDOException $e) {
    // Log server-side and send friendly message
    error_log('start_attempt error: ' . $e->getMessage());
    if (wantsJson()) {
        header('Content-Type: application/json', true, 500);
        echo json_encode(['error' => 'Server error']);
    } else {
        http_response_code(500);
        echo 'An unexpected server error occurred.';
    }
    exit;
}
