<?php
// src/admin/student_attempts.php
require_once __DIR__ . '/../middleware/auth.php';
requireRole('admin');
require_once __DIR__ . '/../config/db.php';

$student_id = intval($_GET['id'] ?? 0);
if ($student_id <= 0) {
    http_response_code(400);
    die('Missing or invalid student id.');
}

// fetch student
$stmt = $pdo->prepare("SELECT id, full_name, email, program, country, status, created_at FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$student) {
    http_response_code(404);
    die('Student not found.');
}

/* Fetch attempts for this student.
   Use LEFT JOIN on exams so attempts remain even if exam row missing. */
$attemptsStmt = $pdo->prepare("
    SELECT
        a.id AS attempt_id,
        a.exam_id,
        a.student_id,
        a.score,
        a.started_at,
        a.submitted_at,
        a.status,
        a.duration_minutes,
        a.created_at AS attempt_created,
        e.total_marks
    FROM attempts a
    LEFT JOIN exams e ON e.id = a.exam_id
    WHERE a.student_id = ?
    ORDER BY a.created_at DESC
");
$attemptsStmt->execute([$student_id]);
$attempts = $attemptsStmt->fetchAll(PDO::FETCH_ASSOC);

/* helper */
function fmtDate($d) {
    if (!$d) return '—';
    $ts = strtotime($d);
    if ($ts === false) return htmlspecialchars($d);
    // nicer format: 2026-01-29 00:48 (use your timezone / format as needed)
    return date('Y-m-d H:i:s', $ts);
}

function humanDuration($seconds) {
    if ($seconds <= 0) return '0s';
    $mins = floor($seconds / 60);
    $secs = $seconds % 60;
    if ($mins > 0) {
        return $mins . 'm ' . $secs . 's';
    }
    return $secs . 's';
}
?>
<?php require __DIR__ . '/../constants/header.php'; ?>
<title>Student Attempts — <?= htmlspecialchars($student['full_name']) ?></title>
</head>
<body class="container body py-4">
    <a href="students.php" class="btn btn-outline-secondary mb-3">← Back</a>

     <div class="card mb-4"  style="background: #042c2c; color: white;">
        <div class="card-body d-flex gap-3 align-items-center">
            <div>
                <h4 class="mb-0"><?= htmlspecialchars($student['full_name']) ?></h4>
                <div class="small text-muted"><?= htmlspecialchars($student['email']) ?></div>
            </div>
            <div class="ms-auto text-end">
                <div class="small text-muted">Program</div>
                <div><?= htmlspecialchars($student['program'] ?? '—') ?></div>
            </div>
        </div>
    </div>

    <?php if (empty($attempts)): ?>
        <div class="alert alert-info">This student has not attempted any exams yet.</div>
    <?php else: ?>
        <div class="table-responsive">
        <table class="table table-bordered table-hover">
            <thead class="table-light">
                <tr>
                    <th>Attempt ID</th>
                    <th>Exam</th>
                    <th class="text-end">Score</th>
                    <th>Status</th>
                    <th>Time Taken</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($attempts as $a): ?>
                    <?php
                        // prefer submitted_at -> started_at -> attempt_created for the 'Date'
                        $dateTaken = $a['submitted_at'] ?: $a['started_at'] ?: $a['attempt_created'];

                        // compute actual time taken in seconds when both timestamps exist
                        $timeTakenText = '—';
                        if (!empty($a['started_at']) && !empty($a['submitted_at'])) {
                            $startTs = strtotime($a['started_at']);
                            $subTs = strtotime($a['submitted_at']);
                            if ($startTs !== false && $subTs !== false && $subTs >= $startTs) {
                                $secs = $subTs - $startTs;
                                $timeTakenText = humanDuration($secs);
                            }
                        }

                        // fallback: use duration_minutes if present (that indicates allocated attempt duration)
                        if ($timeTakenText === '—' && !empty($a['duration_minutes'])) {
                            $timeTakenText = (int)$a['duration_minutes'] . ' min';
                        }

                        $examTitle = $a['exam_title'] ?: ('Exam #' . (int)$a['exam_id']);
                    ?>
                    <tr>
                        <td><?= (int)$a['attempt_id'] ?></td>
                        <td>
                            <div class="fw-semibold"><?= htmlspecialchars($examTitle) ?></div>
                            <?php if (!empty($a['exam_total_marks'])): ?>
                                <div class="small text-muted">Total marks: <?= (int)$a['exam_total_marks'] ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="text-end"><?= is_null($a['score']) ? '—' : (int)$a['score'] ?></td>
                        <td>
                            <span class="badge bg-<?= ($a['status'] === 'completed' || $a['status'] === 'submitted') ? 'success' : 'secondary' ?>">
                                <?= htmlspecialchars(ucfirst($a['status'])) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($timeTakenText) ?></td>
                        <td><?= htmlspecialchars(fmtDate($dateTaken)) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>

</body>
</html>
