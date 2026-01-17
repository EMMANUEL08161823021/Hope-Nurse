<?php
// src/student/result.php

require_once '../middleware/auth.php';
requireRole('student');
require_once '../config/db.php';

$attempt_id = (int)($_GET['attempt_id'] ?? 0);
$student_id = (int)($_SESSION['user']['id'] ?? 0);

if ($attempt_id <= 0) {
    http_response_code(400);
    die('Invalid attempt ID.');
}

/*
|-------------------------------------------------------------------------- 
| Fetch attempt + exam info (secure: student-owned only)
|-------------------------------------------------------------------------- 
*/
$stmt = $pdo->prepare("
    SELECT 
        a.id,
        a.score,
        a.status,
        a.created_at,
        a.started_at,
        a.submitted_at,
        e.title,
        e.total_marks
    FROM attempts a
    JOIN exams e ON a.exam_id = e.id
    WHERE a.id = ? AND a.student_id = ?
    LIMIT 1
");
$stmt->execute([$attempt_id, $student_id]);
$attempt = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$attempt) {
    http_response_code(404);
    die('Result not found or access denied.');
}

/*
|-------------------------------------------------------------------------- 
| Detect which column contains the answer in the answers table.
| Use INFORMATION_SCHEMA.COLUMNS so we can use parameters safely.
|-------------------------------------------------------------------------- 
*/
$possibleCols = ['answer', 'answer_text', 'response'];

// fetch existing columns for the 'answers' table in the current database
$colsStmt = $pdo->prepare("
    SELECT COLUMN_NAME
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'answers'
");
$colsStmt->execute();
$existingCols = $colsStmt->fetchAll(PDO::FETCH_COLUMN);

$answerColumn = null;
foreach ($possibleCols as $col) {
    if (in_array($col, $existingCols, true)) {
        $answerColumn = $col;
        break;
    }
}

if (!$answerColumn) {
    die('Answers table exists but no readable answer column was found. Expected one of: ' . implode(', ', $possibleCols));
}

/*
|-------------------------------------------------------------------------- 
| Fetch answers (robust to answer column naming)
|-------------------------------------------------------------------------- 
*/
$sql = "
    SELECT 
        q.question_text,
        q.question_type,
        an.{$answerColumn} AS answer,
        an.is_correct,
        an.awarded_marks
    FROM answers an
    JOIN questions q ON an.question_id = q.id
    WHERE an.attempt_id = ?
    ORDER BY an.id ASC
";
$ans = $pdo->prepare($sql);
$ans->execute([$attempt_id]);
$answers = $ans->fetchAll(PDO::FETCH_ASSOC);

/* Helper to render dates safely */
function fmtDate($d) {
    if (!$d) return '—';
    $ts = strtotime($d);
    if ($ts === false) return htmlspecialchars($d);
    return date('Y-m-d H:i', $ts);
}
?>
<?php require '../constants/header.php'?>
    <title>Exam Result</title>
</head>
<body class="container py-4">

<!-- =======================
     EXAM SUMMARY
======================== -->
<h3 class="mb-3">Exam Result</h3>

<div class="card mb-4 shadow-sm">
    <div class="card-body">
        <h5 class="card-title">
            <?= htmlspecialchars($attempt['title']) ?>
        </h5>

        <p class="mb-2">
            <strong>Score:</strong>
            <?= (int)$attempt['score'] ?>
            <?php if (!empty($attempt['total_marks'])): ?>
                / <?= (int)$attempt['total_marks'] ?>
            <?php endif; ?>
        </p>

        <p class="mb-2">
            <strong>Status:</strong>
            <span class="badge bg-<?= ($attempt['status'] === 'completed') ? 'success' : 'secondary' ?>">
                <?= htmlspecialchars(ucfirst($attempt['status'])) ?>
            </span>
        </p>

        <p class="mb-0">
            <strong>Date Taken:</strong>
            <?= htmlspecialchars(fmtDate($attempt['submitted_at'] ?? $attempt['created_at'])) ?>
        </p>
    </div>
</div>

<!-- =======================
     ANSWERS BREAKDOWN
======================== -->
<h5 class="mb-3">Answer Breakdown</h5>

<?php if (empty($answers)): ?>
    <div class="alert alert-warning">
        No answers were recorded for this exam.
    </div>
<?php else: ?>

    <?php foreach ($answers as $a): ?>
        <div class="mb-3 p-3 border rounded">
            <div class="mb-1">
                <strong>Question:</strong>
                <?= nl2br(htmlspecialchars($a['question_text'])) ?>
            </div>

            <div class="mb-1">
                <strong>Your Answer:</strong>
                <?php
                    $raw = (string)($a['answer'] ?? '');
                    $decoded = json_decode($raw, true);

                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        // join arrays (e.g., checklist answers)
                        echo htmlspecialchars(implode(', ', $decoded));
                    } else {
                        echo htmlspecialchars($raw);
                    }
                ?>
            </div>

            <div class="mb-1">
                <strong>Correct:</strong>
                <?php
                    // Normalize various DB representations of boolean (0/1, '0'/'1', true/false)
                    $isCorrect = $a['is_correct'];
                    $isCorrectBool = null;
                    if ($isCorrect === null || $isCorrect === '') {
                        $isCorrectBool = null;
                    } else {
                        // treat '1', 1, 'true', true as true
                        $isCorrectBool = filter_var($isCorrect, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                        // FILTER_VALIDATE_BOOLEAN returns null for non-boolean-like strings when using FLAG; handle fallback:
                        if ($isCorrectBool === null) {
                            $isCorrectBool = ($isCorrect == 1 || strtolower((string)$isCorrect) === 'true');
                        }
                    }
                ?>

                <?php if ($isCorrectBool === null): ?>
                    &mdash;
                <?php elseif ($isCorrectBool): ?>
                    <span class="text-success">Yes</span>
                <?php else: ?>
                    <span class="text-danger">No</span>
                <?php endif; ?>
            </div>

            <div>
                <strong>Marks Awarded:</strong>
                <?= number_format((float)($a['awarded_marks'] ?? 0), 2) ?>
            </div>
        </div>
    <?php endforeach; ?>

<?php endif; ?>

<a href="dashboard.php" class="btn btn-secondary mt-4">
    Back to Dashboard
</a>

</body>
</html>
