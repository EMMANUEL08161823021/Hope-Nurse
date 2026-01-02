<?php
require_once '../middleware/auth.php';
requireRole('admin');
require_once '../config/db.php';

$exam_id = (int)($_GET['id'] ?? 0);

// Fetch exam
$stmt = $pdo->prepare("
    SELECT exams.*, users.full_name AS admin_name
    FROM exams
    JOIN users ON exams.created_by = users.id
    WHERE exams.id = ?
");
$stmt->execute([$exam_id]);
$exam = $stmt->fetch();

if (!$exam) {
    die("Exam not found");
}


$stmt = $pdo->prepare("SELECT * FROM questions WHERE exam_id = ?");
$stmt->execute([$exam_id]);
$questions = $stmt->fetchAll();

// Count questions
$qCountStmt = $pdo->prepare("SELECT COUNT(*) FROM questions WHERE exam_id = ?");
$qCountStmt->execute([$exam_id]);
$totalQuestions = $qCountStmt->fetchColumn();
?>
<?php require '../constants/header.php'?>
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

            <div class="row">
                <div class="col-md-4">
                    <strong>Status:</strong><br>
                    <span class="badge bg-<?=
                        $exam['status'] === 'in_progress' ? 'success' :
                        ($exam['status'] === 'closed' ? 'danger' : 'secondary')
                    ?>">
                        <?= ucfirst($exam['status']) ?>
                    </span>
                </div>

                <div class="col-md-4">
                    <strong>Created By:</strong><br>
                    <?= htmlspecialchars($exam['admin_name']) ?>
                </div>

                <div class="col-md-4">
                    <strong>Created On:</strong><br>
                    <?= $exam['created_at'] ?>
                </div>
            </div>

            <hr>

            <div class="row">
                <div class="col-md-4">
                    <strong>Total Questions:</strong><br>
                    <?= $totalQuestions ?>
                </div>

                <div class="col-md-4">
                    <strong>Duration:</strong><br>
                    <?= $exam['duration'] ?? 'N/A' ?> minutes
                </div>

                <div class="col-md-4">
                    <strong>Total Marks:</strong><br>
                    <?= $exam['total_marks'] ?? 'Auto-calculated' ?>
                </div>
            </div>

            <hr>

            <!-- ACTIONS -->
            <div class="d-flex gap-2">

                <a href="questions.php?exam_id=<?= $exam_id ?>"
                   class="btn btn-primary">
                    Manage Questions
                </a>

                <?php if ($exam['status'] !== 'in_progress'): ?>
                    <a href="exam_toggle.php?id=<?= $exam_id ?>&action=start"
                       class="btn btn-success">
                        Start Exam
                    </a>
                <?php endif; ?>

                <?php if ($exam['status'] === 'in_progress'): ?>
                    <a href="exam_toggle.php?id=<?= $exam_id ?>&action=close"
                       class="btn btn-danger">
                        Close Exam
                    </a>
                <?php endif; ?>

            </div>


            <div class="container mt-4">

                <h3>Questions</h3>
                <a href="add_question.php?exam_id=<?= $exam_id ?>" class="btn btn-success mb-3">
                    + Add Question
                </a>

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
                        <?php if (!$questions): ?>
                            <tr><td colspan="4" class="text-center">No questions yet</td></tr>
                        <?php endif; ?>

                        <?php foreach ($questions as $q): ?>
                            <tr>
                                <td><?= htmlspecialchars($q['question_text']) ?></td>
                                <td><?= ucfirst(str_replace('_',' ', $q['question_type'])) ?></td>
                                <td><?= $q['marks'] ?></td>
                                <td>
                                    <a href="delete_question.php?id=<?= $q['id'] ?>&exam_id=<?= $exam_id ?>"
                                    class="btn btn-danger btn-sm"
                                    onclick="return confirm('Delete question?')">
                                        Delete
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

            </div>

        </div>
    </div>

</div>

</body>
</html>
