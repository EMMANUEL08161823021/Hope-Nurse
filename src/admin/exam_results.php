<?php
require_once __DIR__ . '/../middleware/auth.php';
requireRole('admin');
require_once __DIR__ . '/../config/db.php';

$exam_id = (int)($_GET['exam_id'] ?? 0);
if ($exam_id <= 0) {
    http_response_code(400);
    die('Invalid exam id.');
}

$examStmt = $pdo->prepare("
    SELECT
        e.id,
        e.total_marks,
        e.status,
        e.created_at,
        e.duration,
        e.results_released,
        c.title AS course_title,
        c.description AS course_description,
        p.name AS program_name,
        u.full_name AS admin_name
    FROM exams e
    LEFT JOIN courses c ON e.course_id = c.id
    LEFT JOIN programs p ON e.program_id = p.id
    LEFT JOIN users u ON e.created_by = u.id
    WHERE e.id = ?
    LIMIT 1
");
$examStmt->execute([$exam_id]);
$exam = $examStmt->fetch(PDO::FETCH_ASSOC);
if (!$exam) {
    http_response_code(404);
    die('Exam not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_results'])) {
    $newState = $exam['results_released'] ? 0 : 1;

    $stmt = $pdo->prepare("
        UPDATE exams
        SET results_released = ?
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$newState, $exam_id]);

    header("Location: exam_results.php?exam_id=" . $exam_id);
    exit;
}

$q = trim((string)($_GET['q'] ?? ''));
$filterSql = '';
$params = [$exam_id];
if ($q !== '') {
    $filterSql = " AND (u.full_name LIKE ? OR u.email LIKE ?)";
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
}
function gradeFromPercent(float $pct): string {
    if ($pct >= 70.0) return 'A';
    if ($pct >= 60.0) return 'B';
    if ($pct >= 50.0) return 'C';
    if ($pct >= 45.0) return 'D';
    if ($pct >= 40.0) return 'E';
    return 'F';
}

$attemptsStmt = $pdo->prepare("
    SELECT
        a.id AS attempt_id,
        a.student_id,
        a.score,
        a.status AS attempt_status,
        a.started_at,
        a.submitted_at,
        a.created_at AS attempt_created,
        a.duration_minutes,
        u.full_name AS student_name,
        u.email AS student_email
    FROM attempts a
    JOIN users u ON a.student_id = u.id
    WHERE a.exam_id = ? {$filterSql}
    ORDER BY a.created_at DESC
");
$attemptsStmt->execute($params);
$attempts = $attemptsStmt->fetchAll(PDO::FETCH_ASSOC);


function fmtDate($d) {
    if (empty($d)) return '—';
    $ts = strtotime($d);
    if ($ts === false) return htmlspecialchars($d);
    return date('Y-m-d H:i', $ts);
}
?>
<?php require __DIR__ . '/../constants/header.php'; ?>
<title>Exam Results — <?= htmlspecialchars($exam['course_title'] ?? 'Exam') ?></title>
</head>

<style>
    .body {
        background: #042c2c;
    }
</style>
<body class="container body py-4">


<a href="dashboard.php" class="btn btn-secondary rounded">Back</a>


<div class="card mt-4">
    <div class="p-4" style="background-color: #042c2c; color: #fff;">
        <h3><?= htmlspecialchars($exam['course_title'] ?? 'Untitled course') ?></h3>
  
        <?php if (!empty($exam['course_description'])): ?>
            <div class="small text-muted mt-1"><?= nl2br(htmlspecialchars($exam['course_description'])) ?></div>
        <?php endif; ?>
    </div>
    <div class="card-body">

        <div class="text-muted mb-2">

            <div class="mt-3 p-3 rounded">
                <div class="d-flex flex-wrap align-items-center justify-content-between text-muted small fw-medium">
                    <!-- Total Marks -->
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-check-circle-fill text-primary"></i>
                        <span>Total Marks:</span>
                        <strong><?= htmlspecialchars($exam['total_marks'] ?? 'N/A') ?></strong>
                    </div>

                    <!-- Status -->
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-info-circle-fill text-secondary"></i>
                        <span>Status:</span>
                        <?php
                        $status = $exam['status'] ?? '';
                        $badgeClass = match ($status) {
                            'in_progress' => 'bg-success',
                            'closed'      => 'bg-danger',
                            default       => 'bg-secondary',
                        };
                        ?>
                        <span class="badge <?= $badgeClass ?> px-3 py-2 rounded-pill">
                            <?= htmlspecialchars(ucfirst($status)) ?>
                        </span>
                    </div>

                    <!-- Created By -->
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-person-fill text-info"></i>
                        <span>Created By:</span>
                        <strong><?= htmlspecialchars($exam['admin_name'] ?? '—') ?></strong>
                    </div>

                    <!-- Created At -->
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-calendar-event text-warning"></i>
                        <span>Created At:</span>
                        <?php
                        $createdAt = $exam['created_at'] ?? null;
                        $formattedDate = $createdAt ? date('d M Y, H:i', strtotime($createdAt)) : '—';
                        ?>
                        <strong><?= htmlspecialchars($formattedDate) ?></strong>
                    </div>
                </div>
            </div>

            <div class="my-4">
                <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <h6 class="mb-1">Student Result Visibility</h6>
                        <small class="text-muted">
                            <?= $exam['results_released']
                                ? 'Students can currently view their results.'
                                : 'Results are hidden from students.' ?>
                        </small>
                    </div>

                    <form method="post">
                        <button
                            type="submit"
                            name="toggle_results"
                            class="btn <?= $exam['results_released'] ? 'btn-danger' : 'btn-success' ?>"
                            onclick="return confirm('Are you sure you want to <?= $exam['results_released'] ? 'hide' : 'release' ?> results?');"
                        >
                            <?= $exam['results_released'] ? 'Hide Results' : 'Release Results' ?>
                        </button>
                    </form>
                </div>
            </div>


        </div>

        <form method="get" class="d-flex">
            <input type="hidden" name="exam_id" value="<?= (int)$exam['id'] ?>">
            <div class="col-auto" style="width: 90%;">
                <input type="search" name="q" class="form-control" placeholder="Search student name or email" value="<?= htmlspecialchars($q) ?>">
            </div>
            <div class="col-auto" style="width: 5%;">
                <button class="btn btn-outline-primary" type="submit" style="width: 100%;">
                    <i class="bi bi-search me-2"></i>
                </button>
            </div>
            <div class="col-auto" style="width: 5%;">
                <a href="exam_results.php?exam_id=<?= (int)$exam['id'] ?>" 
                class="btn btn-danger text-white" style="width: 100%;">
                    <i class="bi bi-trash me-2" aria-hidden="true"></i>
                </a>
            </div>
      
        </form>

        <h6 class="mt-3">Attempts (<?= count($attempts) ?>)</h6>

        <?php if (empty($attempts)): ?>
            <div class="alert alert-info">No attempts found for this exam.</div>
        <?php else: ?>
            <div class="table-responsive">
            <table class="table table-bordered table-striped align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Student</th>
                        <th>Email</th>
                        <th>Score</th>
                        <th>Grade</th>
                        <th>Status</th>
                        <th>Time Taken</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($attempts as $a): ?>
                        <?php
                            // Prefer submitted_at, then started_at, then created_at
                            $dateTaken = $a['submitted_at'] ?: $a['started_at'] ?: $a['attempt_created'];

                            // compute grade if possible
                            $gradeDisplay = '—';
                            $pctDisplay = null;
                            if (isset($a['score']) && $a['score'] !== null && !empty($exam['total_marks']) && ((int)$exam['total_marks']) > 0) {
                                $score = (float)$a['score'];
                                $total = (float)$exam['total_marks'];
                                $pct = ($total > 0) ? ($score / $total) * 100.0 : 0.0;
                                if ($pct < 0) $pct = 0;
                                if ($pct > 100) $pct = 100;
                                $grade = gradeFromPercent($pct);
                                $gradeDisplay = htmlspecialchars($grade);
                                $pctDisplay = number_format($pct, 1) . '%';
                            }
                        ?>
                        <?php
                            $dateTaken = $a['submitted_at'] ?: $a['started_at'] ?: $a['attempt_created'];
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($a['student_name'] ?: '—') ?></td>
                            <td><?= htmlspecialchars($a['student_email'] ?: '—') ?></td>
                            <td>
                                <?= is_null($a['score']) ? '—' : (int)$a['score'] ?>
                                <?php if (!empty($exam['total_marks'])): ?>/ <?= (int)$exam['total_marks'] ?><?php endif; ?>
                            </td>
                            <td>
                                <?php if ($gradeDisplay === '—'): ?>
                                    &mdash;
                                <?php else: ?>
                                    <strong><?= $gradeDisplay ?></strong>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-<?= in_array($a['attempt_status'], ['submitted','auto_submitted']) ? 'success' : 'secondary' ?>">
                                    <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $a['attempt_status']))) ?>
                                </span>
                            </td>
                            <?php
        
                            $timeTakenStr = '—';

                            if (!empty($a['submitted_at']) && !empty($a['started_at'])) {
                                try {
                                    $start = new DateTime($a['started_at']);
                                    $end   = new DateTime($a['submitted_at']);
                                    $diff  = $end->getTimestamp() - $start->getTimestamp();
                                    if ($diff < 0) $diff = 0;

                                    $hours = floor($diff / 3600);
                                    $minutes = floor(($diff % 3600) / 60);
                                    $seconds = $diff % 60;

                                    if ($hours > 0) {
                                        // H:MM:SS
                                        $timeTakenStr = sprintf('%d:%02d:%02d', $hours, $minutes, $seconds);
                                    } elseif ($minutes > 0) {
                                        // Xm Ys
                                        $timeTakenStr = sprintf('%d min %d s', $minutes, $seconds);
                                    } else {
                                        // Xs
                                        $timeTakenStr = sprintf('%d s', $seconds);
                                    }
                                } catch (Exception $e) {
                                    // keep fallback below
                                    $timeTakenStr = '—';
                                }
                            } elseif (!empty($a['duration_minutes'])) {
                                // fallback to recorded duration (if you prefer not to show this, remove this branch)
                                $timeTakenStr = (int)$a['duration_minutes'] . ' min';
                            }
                            ?>
                            <td><?= htmlspecialchars($timeTakenStr) ?></td>

                            <td><?= htmlspecialchars(fmtDate($dateTaken)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>

    </div>
</div>

</body>
</html>
