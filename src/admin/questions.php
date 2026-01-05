<?php
require_once '../middleware/auth.php';
requireRole('admin');
require_once '../config/db.php';

$exam_id = (int)($_GET['exam_id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM questions WHERE exam_id = ?");
$stmt->execute([$exam_id]);
$questions = $stmt->fetchAll();
?>
<?php require '../constants/header.php'?>
    <title>Questions</title>
</head>
<body>
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
</body>
</html>
