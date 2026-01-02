<?php
require_once __DIR__ . '/../middleware/auth.php';
requireRole('admin');

require_once '../config/db.php';

/* ===== METRICS ===== */

// Total exams
$totalExams = $pdo->query("SELECT COUNT(*) FROM exams")->fetchColumn();

// Active exams
$activeExams = $pdo->query("SELECT COUNT(*) FROM exams WHERE status='in_progress'")->fetchColumn();

// Total students
$totalStudents = $pdo->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();

// Total questions
$totalQuestions = $pdo->query("SELECT COUNT(*) FROM questions")->fetchColumn();

// Exam attempts (optional table)
try {
    $totalAttempts = $pdo->query("SELECT COUNT(*) FROM exam_attempts")->fetchColumn();
} catch (Exception $e) {
    $totalAttempts = 0;
}

// Recent exams
$recentExamsStmt = $pdo->query("
    SELECT id, title, status, created_at 
    FROM exams 
    ORDER BY created_at DESC 
    LIMIT 5
");
$recentExams = $recentExamsStmt->fetchAll();
?>

<?php require '../constants/header.php'?>
     <title>Admin Dashboard</title>
</head>
<body>

<div class="container mt-4">
    <h2>Admin Dashboard</h2>
    <p class="text-muted">Exam Management Overview</p>

    <!-- METRICS -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-center shadow-sm">
                <div class="card-body">
                    <h6>Total Exams</h6>
                    <h3><?= $totalExams ?></h3>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card text-center shadow-sm">
                <div class="card-body">
                    <h6>Active Exams</h6>
                    <h3><?= $activeExams ?></h3>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card text-center shadow-sm">
                <div class="card-body">
                    <h6>Total Students</h6>
                    <h3><?= $totalStudents ?></h3>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card text-center shadow-sm">
                <div class="card-body">
                    <h6>Total Questions</h6>
                    <h3><?= $totalQuestions ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- OPTIONAL ATTEMPTS -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-center shadow-sm">
                <div class="card-body">
                    <h6>Exam Attempts</h6>
                    <h3><?= $totalAttempts ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- QUICK ACTIONS -->
    <div class="mb-4">
        <!-- <a href="exams.php" class="btn btn-primary">Manage Exams</a> -->
        <a href="students.php" class="btn btn-secondary">Manage Students</a>
        <a href="create_exam.php" class="btn btn-success">Create Exam</a>
    </div>

    <!-- RECENT EXAMS -->
    <h4>Recent Exams</h4>

    <table class="table table-bordered mt-2">
        <thead>
            <tr>
                <th>Title</th>
                <th>Status</th>
                <th>Created</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($recentExams) === 0): ?>
                <tr>
                    <td colspan="4" class="text-center">No exams found</td>
                </tr>
            <?php else: ?>
                <?php foreach ($recentExams as $exam): ?>
                    <tr>
                        <td><?= htmlspecialchars($exam['title']) ?></td>
                        <td>
                            <span class="badge bg-<?=
                                $exam['status'] === 'in_progress' ? 'success' :
                                ($exam['status'] === 'closed' ? 'danger' : 'secondary')
                            ?>">
                                <?= ucfirst($exam['status']) ?>
                            </span>
                        </td>
                        <td><?= $exam['created_at'] ?></td>
                        <td>
                            <a href="exams_view.php?id=<?= $exam['id'] ?>" class="btn btn-sm btn-outline-primary">
                                View
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

</div>
<a href="../auth/logout.php">Logout</a>

</body>
</html>


