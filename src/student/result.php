<?php
// src/student/result.php
require_once '../middleware/auth.php';
if ($_SESSION['user']['role'] !== 'student') die('Forbidden');
require_once '../config/db.php';

$attempt_id = intval($_GET['attempt_id'] ?? 0);
$stmt = $pdo->prepare("SELECT a.*, e.title FROM attempts a JOIN exams e ON a.exam_id=e.id WHERE a.id=? AND a.student_id=?");
$stmt->execute([$attempt_id, $_SESSION['user']['id']]);
$attempt = $stmt->fetch();
if (!$attempt) die('Not found');

$ans = $pdo->prepare("SELECT q.question_text, q.question_type, an.answer_text, an.is_correct, an.awarded_marks FROM answers an JOIN questions q ON an.question_id=q.id WHERE an.attempt_id = ?");
$ans->execute([$attempt_id]);
$answers = $ans->fetchAll();
?>
<!doctype html><html><head><link rel="stylesheet" href="/assets/bootstrap.min.css"></head><body class="container py-4">
<h3>Result — <?=htmlspecialchars($attempt['title'])?></h3>
<p>Score: <strong><?= htmlspecialchars($attempt['score']) ?></strong></p>
<p>Status: <strong><?= htmlspecialchars($attempt['status']) ?></strong></p>
<hr>
<?php foreach ($answers as $a): ?>
  <div class="mb-3">
    <div><strong>Q:</strong> <?= htmlspecialchars($a['question_text']) ?></div>
    <div><strong>Your answer:</strong> <?= htmlspecialchars($a['answer_text']) ?></div>
    <div><strong>Marks awarded:</strong> <?= htmlspecialchars($a['awarded_marks']) ?></div>
  </div>
<?php endforeach; ?>
</body></html>
