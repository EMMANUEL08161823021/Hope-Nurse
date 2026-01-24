<?php
// src/admin/exam_results.php
require_once __DIR__ . '/../middleware/auth.php';
requireRole('admin');
require_once __DIR__ . '/../config/db.php';

$exam_id = (int)($_GET['exam_id'] ?? 0);
if ($exam_id <= 0) {
    http_response_code(400);
    die('Invalid exam id.');
}

/* Fetch exam with course & program info (defensive joins) */
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

/* Optional search/filter by student name or email (GET q) */
$q = trim((string)($_GET['q'] ?? ''));
$filterSql = '';
$params = [$exam_id];
if ($q !== '') {
    $filterSql = " AND (u.full_name LIKE ? OR u.email LIKE ?)";
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
}

/* Fetch attempts with student info — using your exact attempt columns */
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


/* small date helper */
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
<body class="container py-4">

<a href="exams_view.php?id=<?= (int)$exam['id'] ?>" class="btn btn-outline-secondary mb-3 ms-2">Back to Exam</a>

<div class="card mb-4">
    <div class="card-body">
        <h4 class="card-title mb-1"><?= htmlspecialchars($exam['course_title'] ?? 'Untitled Course') ?></h4>
        <div class="text-muted mb-2">
            <small><?= htmlspecialchars($exam['program_name'] ?? '') ?></small>
            <?php if (!empty($exam['course_description'])): ?>
                <div class="small mt-1"><?= nl2br(htmlspecialchars($exam['course_description'])) ?></div>
            <?php endif; ?>

            <div class="mt-2">
                Total marks: <?= htmlspecialchars($exam['total_marks'] ?? 'N/A') ?> &nbsp; • &nbsp;
                Status:
                <span class="badge bg-<?= ($exam['status'] === 'in_progress') ? 'success' : (($exam['status'] === 'closed') ? 'danger' : 'secondary') ?>">
                    <?= htmlspecialchars(ucfirst($exam['status'] ?? '')) ?>
                </span>
                &nbsp; • &nbsp;
                Created by: <?= htmlspecialchars($exam['admin_name'] ?? '—') ?>
                &nbsp; • &nbsp;
                Created at: <?= htmlspecialchars($exam['created_at'] ?? '—') ?>
            </div>

            <form method="post" class="mt-3">
                <button
                    type="submit"
                    name="toggle_results"
                    class="btn btn-sm <?= $exam['results_released'] ? 'btn-danger' : 'btn-success' ?>"
                    onclick="return confirm('Are you sure you want to <?= $exam['results_released'] ? 'hide' : 'release' ?> results?');"
                >
                    <?= $exam['results_released'] ? 'Hide Results' : 'Release Results' ?>
                </button>

                <span class="ms-2 small text-muted">
                    <?= $exam['results_released'] ? 'Students can view results' : 'Results are hidden from students' ?>
                </span>
            </form>

        </div>

        <form method="get" class="row g-2 align-items-center mb-3" style="max-width:560px;">
            <input type="hidden" name="exam_id" value="<?= (int)$exam['id'] ?>">
            <div class="col-auto">
                <input type="search" name="q" class="form-control" placeholder="Search student name or email" value="<?= htmlspecialchars($q) ?>">
            </div>
            <div class="col-auto">
                <button class="btn btn-outline-primary" type="submit">Search</button>
            </div>
            <?php if ($q !== ''): ?>
                <div class="col-auto">
                    <a href="exam_results.php?exam_id=<?= (int)$exam['id'] ?>" class="btn btn-outline-secondary">Clear</a>
                </div>
            <?php endif; ?>
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
                        <th class="text-end">Score</th>
                        <th>Status</th>
                        <th>Time Taken</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($attempts as $a): ?>
                        <?php
                            // Prefer submitted_at, then started_at, then created_at
                            $dateTaken = $a['submitted_at'] ?: $a['started_at'] ?: $a['attempt_created'];
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($a['student_name'] ?: '—') ?></td>
                            <td><?= htmlspecialchars($a['student_email'] ?: '—') ?></td>
                            <td class="text-end">
                                <?= is_null($a['score']) ? '—' : (int)$a['score'] ?>
                                <?php if (!empty($exam['total_marks'])): ?>/ <?= (int)$exam['total_marks'] ?><?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-<?= in_array($a['attempt_status'], ['submitted','auto_submitted']) ? 'success' : 'secondary' ?>">
                                    <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $a['attempt_status']))) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars(!empty($a['duration_minutes']) ? (int)$a['duration_minutes'] . ' min' : '—') ?></td>
                            <td><?= htmlspecialchars(fmtDate($dateTaken)) ?></td>
                            <td>
                                <a href="attempt_view.php?attempt_id=<?= (int)$a['attempt_id'] ?>" class="btn btn-sm btn-outline-primary">
                                    View Result
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>

    </div>
</div>

<a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
</body>
</html>
