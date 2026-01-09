<?php
require_once '../middleware/auth.php';
requireRole('admin');
require_once '../config/db.php';

$exam_id = (int)($_GET['id'] ?? 0);
if ($exam_id <= 0) {
    die('Invalid exam id');
}

// Fetch exam + admin name
$stmt = $pdo->prepare("
    SELECT exams.*, users.full_name AS admin_name
    FROM exams
    JOIN users ON exams.created_by = users.id
    WHERE exams.id = ?
    LIMIT 1
");
$stmt->execute([$exam_id]);
$exam = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$exam) die("Exam not found");

// Questions
$qStmt = $pdo->prepare("SELECT * FROM questions WHERE exam_id = ? ORDER BY id ASC");
$qStmt->execute([$exam_id]);
$questions = $qStmt->fetchAll(PDO::FETCH_ASSOC);

// Counts
$qCountStmt = $pdo->prepare("SELECT COUNT(*) FROM questions WHERE exam_id = ?");
$qCountStmt->execute([$exam_id]);
$totalQuestions = (int)$qCountStmt->fetchColumn();

$attemptsStmt = $pdo->prepare("SELECT COUNT(*) FROM attempts WHERE exam_id = ?");
$attemptsStmt->execute([$exam_id]);
$attemptCount = (int)$attemptsStmt->fetchColumn();
?>
<?php require '../constants/header.php' ?>
    <title>View Exam</title>
</head>
<body>

<div class="container mt-4">

    <a href="dashboard.php" class="btn btn-secondary mb-3">← Back to Exams</a>

    <div class="card shadow-sm">
        <div class="card-body">

            <h3><?= htmlspecialchars($exam['title']) ?></h3>
            <p class="text-muted"><?= htmlspecialchars($exam['description'] ?? 'No description') ?></p>

            <hr>

            <div class="row mb-3">
                <div class="col-md-3">
                    <strong>Status</strong><br>
                    <span class="badge bg-<?= 
                        $exam['status'] === 'in_progress' ? 'success' :
                        ($exam['status'] === 'closed' ? 'danger' : 'secondary')
                    ?>">
                        <?= htmlspecialchars(ucfirst($exam['status'])) ?>
                    </span>
                </div>

                <div class="col-md-3">
                    <strong>Created By</strong><br>
                    <?= htmlspecialchars($exam['admin_name']) ?>
                </div>

                <div class="col-md-3">
                    <strong>Created On</strong><br>
                    <?= htmlspecialchars($exam['created_at']) ?>
                </div>

                <div class="col-md-3">
                    <strong>Attempts</strong><br>
                    <?= $attemptCount ?>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-4">
                    <strong>Total Questions:</strong><br>
                    <?= $totalQuestions ?>
                </div>

                <div class="col-md-4">
                    <strong>Duration:</strong><br>
                    <?= htmlspecialchars($exam['duration'] ?? 'N/A') ?> minutes
                </div>

                <div class="col-md-4">
                    <strong>Total Marks:</strong><br>
                    <?= htmlspecialchars($exam['total_marks'] ?? 'Auto-calculated') ?>
                </div>
            </div>

            <hr>

            <!-- ACTIONS: Manage Questions | Start / Close | Delete (if safe) -->
            <div class="d-flex gap-2 mb-3">

                <a href="questions.php?exam_id=<?= $exam_id ?>" class="btn btn-primary">
                    Manage Questions
                </a>

                <a href="exam_toggle.php?id=<?= $exam_id ?>&action=start"
                class="btn btn-success">
                Start Exam
                </a>
                <a href="exam_toggle.php?id=<?= $exam_id ?>&action=close"
                class="btn btn-danger">
                Close Exam
                </a>

                <?php if ($exam['status'] !== 'in_progress'): ?>
                    <?php if ($attemptCount === 0): ?>
                        <a href="exam_delete.php?id=<?= $exam_id ?>"
                           class="btn btn-outline-danger"
                           onclick="return confirm('Delete this exam and all its questions? This cannot be undone.')">
                           Delete Exam
                        </a>
                    <?php else: ?>
                        <button class="btn btn-outline-secondary" disabled
                                title="Cannot delete: students have attempted this exam">
                            Delete Exam
                        </button>
                    <?php endif; ?>
                <?php endif; ?>

            </div>

            <div class="mt-4">
                <h4>Questions</h4>
                <a href="add_question.php?exam_id=<?= $exam_id ?>" class="btn btn-success mb-3">+ Add Question</a>

                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Question</th>
                            <th>Type</th>
                            <th>Marks</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($questions)): ?>
                            <tr><td colspan="4" class="text-center">No questions yet</td></tr>
                        <?php else: ?>
                            <?php foreach ($questions as $q): ?>
                                <tr>
                                    <td><?= htmlspecialchars($q['question_text']) ?></td>
                                    <td><?= htmlspecialchars(ucfirst(str_replace('_',' ', $q['question_type']))) ?></td>
                                    <td><?= (int)$q['marks'] ?></td>
                                    <td>
                                        <a href="delete_question.php?id=<?= (int)$q['id'] ?>&exam_id=<?= $exam_id ?>"
                                           class="btn btn-danger btn-sm"
                                           onclick="return confirm('Delete question?')">
                                           Delete
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>

</div>

</body>
</html>
