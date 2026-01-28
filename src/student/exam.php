<?php
require_once  '../middleware/auth.php';
requireRole('student');

require_once '../config/db.php';

$studentId = (int)($_SESSION['user']['id'] ?? 0);

// Resolve the student's program id (best-effort)
$programId = null;
try {
    // If session stores program name, prefer that
    if (!empty($_SESSION['user']['program'])) {
        $progName = $_SESSION['user']['program'];
        $pStmt = $pdo->prepare("SELECT id FROM programs WHERE name = :name LIMIT 1");
        $pStmt->execute([':name' => $progName]);
        $pRow = $pStmt->fetch(PDO::FETCH_ASSOC);
        if ($pRow && !empty($pRow['id'])) {
            $programId = (int)$pRow['id'];
        }
    }

    // Fallback: try to resolve from users table (if session didn't include program)
    if ($programId === null && $studentId > 0) {
        $pStmt = $pdo->prepare("
            SELECT p.id AS pid
            FROM users u
            LEFT JOIN programs p ON u.program = p.name
            WHERE u.id = :uid
            LIMIT 1
        ");
        $pStmt->execute([':uid' => $studentId]);
        $pRow = $pStmt->fetch(PDO::FETCH_ASSOC);
        if ($pRow && !empty($pRow['pid'])) {
            $programId = (int)$pRow['pid'];
        }
    }
} catch (Throwable $e) {
    error_log('[available_exams] Program resolution failed: ' . $e->getMessage());
    $programId = null;
}

$exams = [];
$errorMsg = null;

if ($programId === null) {
    $errorMsg = "You are not assigned to a program. Please contact support.";
} else {
    // Load exams for this program that the student has not submitted yet
    try {
        $sql = "
            SELECT
                e.id AS exam_id,
                e.duration,
                e.total_marks,
                e.status,
                e.created_at,
                c.title AS course_title,
                c.description AS course_description
            FROM exams e
            LEFT JOIN courses c ON e.course_id = c.id
            WHERE e.status = 'in_progress'
              AND e.program_id = :pid
              AND e.id NOT IN (
                  SELECT exam_id
                  FROM attempts
                  WHERE student_id = :sid
                    AND status IN ('submitted','auto_submitted')
              )
            ORDER BY c.title ASC, e.created_at DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':pid' => $programId, ':sid' => $studentId]);
        $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('[available_exams] Failed to load exams: ' . $e->getMessage());
        $errorMsg = "Unable to load available exams at this time. Please try again later.";
        $exams = [];
    }
}
?>
<?php require '../constants/header.php'?>
<title>Available Exams</title>
</head>
<body class="">

<h3 class="fs-5 fw-semibold mb-3">Available Exams</h3>

    <?php if ($errorMsg): ?>
        <div class="alert alert-warning"><?= htmlspecialchars($errorMsg) ?></div>
    <?php elseif (empty($exams)): ?>
        <div style="background-color: #eab32e;" class="alert alert-info">
            No available exams at the moment.
        </div>
    <?php else: ?>
        <div class="table-responsive">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Course Title</th>
                    <th>Duration (mins)</th>
                    <th>Total Marks</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($exams as $exam): ?>
                <tr>
                    <td>
                        <?= htmlspecialchars($exam['course_title'] ?? 'Exam #' . (int)$exam['exam_id']) ?>
                    </td>
                    <td><?= (int)($exam['duration'] ?? 0) ?></td>
                    <td><?= (int)($exam['total_marks'] ?? 0) ?></td>
                    <td>
                        <?php if (($exam['status'] ?? '') === 'in_progress'): ?>
                            <a href="instructions.php?exam_id=<?= (int)$exam['exam_id'] ?>"
                               class="btn btn-sm btn-success">
                                Start Exam
                            </a>
                        <?php else: ?>
                            <button class="btn btn-sm btn-secondary" disabled>
                                <?= htmlspecialchars(ucfirst($exam['status'] ?? 'Unavailable')) ?>
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>

</body>
</html>
