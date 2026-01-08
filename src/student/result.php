<?php
require_once '../middleware/auth.php';
requireRole('student');
require_once '../config/db.php';

$attempt_id = (int)($_GET['attempt_id'] ?? 0);
$student_id = $_SESSION['user']['id'];

if ($attempt_id <= 0) {
    die('Invalid attempt ID');
}

$stmt = $pdo->prepare("
    SELECT a.*, e.title
    FROM attempts a
    JOIN exams e ON a.exam_id = e.id
    WHERE a.id = ? AND a.student_id = ?
");
$stmt->execute([$attempt_id, $student_id]);
$attempt = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$attempt) {
    die('Result not found or access denied.');
}

$ans = $pdo->prepare("
    SELECT 
        q.question_text,
        q.question_type,
        an.answer,
        an.is_correct,
        an.awarded_marks
    FROM answers an
    JOIN questions q ON an.question_id = q.id
    WHERE an.attempt_id = ?
");
$ans->execute([$attempt_id]);
$answers = $ans->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Exam Result</title>
    <link rel="stylesheet" href="/assets/bootstrap.min.css">
</head>
<body class="container py-4">

<h3>Result — <?= htmlspecialchars($attempt['title']) ?></h3>

<p>
    <strong>Score:</strong> <?= $attempt['score'] ?><br>
    <strong>Status:</strong> <?= htmlspecialchars($attempt['status']) ?>
</p>

<hr>

<?php if (!$answers): ?>
    <div class="alert alert-warning">No answers recorded.</div>
<?php endif; ?>

<?php foreach ($answers as $a): ?>
    <div class="mb-3 p-3 border rounded">
        <div><strong>Question:</strong> <?= htmlspecialchars($a['question_text']) ?></div>
        <div><strong>Your answer:</strong> <?= htmlspecialchars($a['answer']) ?></div>
        <div><strong>Marks awarded:</strong> <?= $a['awarded_marks'] ?></div>
    </div>
<?php endforeach; ?>

<a href="dashboard.php" class="btn btn-secondary mt-3">Back to Dashboard</a>

</body>
</html>
