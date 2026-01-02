<?php
// src/student/instructions.php
require_once __DIR__ . '../middleware/auth.php';
if ($_SESSION['user']['role'] !== 'student') { die('Forbidden'); }
require_once __DIR__ . '../config/db.php';

$exam_id = (int)($_GET['exam_id'] ?? 0);
if (!$exam_id) { die('Missing exam id'); }

// fetch exam
$stmt = $pdo->prepare("SELECT * FROM exams WHERE id = ?");
$stmt->execute([$exam_id]);
$exam = $stmt->fetch();
if (!$exam) { die('Exam not found'); }

// optional: check assignment or other business rules here

?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Instructions — <?=htmlspecialchars($exam['title'])?></title>
  <link rel="stylesheet" href="/assets/bootstrap.min.css">
</head>
<body class="container py-4">
  <h3><?=htmlspecialchars($exam['title'])?></h3>
  <p><?=nl2br(htmlspecialchars($exam['description'] ?? 'No description'))?></p>

  <ul>
    <li>Duration: <strong><?= (int)$exam['duration'] ?> minutes</strong></li>
    <li>Total marks: <strong><?= (int)$exam['total_marks'] ?></strong></li>
    <li>Do not refresh the page — answers are autosaved.</li>
  </ul>

  <form method="post" action="/src/api/start_attempt.php">
    <input type="hidden" name="exam_id" value="<?= $exam_id ?>">
    <button class="btn btn-primary">Start Exam</button>
    <a href="/student/dashboard.php" class="btn btn-secondary">Back</a>
  </form>
</body>
</html>
