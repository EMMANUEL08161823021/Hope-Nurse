<?php
// src/student/instructions.php
require_once '../middleware/auth.php';
if ($_SESSION['user']['role'] !== 'student') { die('Forbidden'); }
require_once '../config/db.php';

$exam_id = (int)($_GET['exam_id'] ?? 0);
if ($exam_id <= 0) { die('Missing exam id'); }


$stmt = $pdo->prepare("
    SELECT
        e.id,
        e.duration,
        e.total_marks,
        e.status,
        c.title   AS course_title,
        c.description AS course_description
    FROM exams e
    LEFT JOIN courses c ON e.course_id = c.id
    WHERE e.id = ?
    LIMIT 1
");
$stmt->execute([$exam_id]);
$exam = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$exam) {
    die('Exam not found');
}

// Optional: only allow active exams
if ($exam['status'] !== 'in_progress') {
    die('This exam is not currently available.');
}
?>
<?php require '../constants/header.php' ?>
<title>Instructions — <?= htmlspecialchars($exam['course_title'] ?? 'Exam') ?></title>
</head>
<body class="container py-4">

<h3><?= htmlspecialchars($exam['course_title'] ?? 'Exam') ?></h3>

<p class="text-muted">
    <?= nl2br(htmlspecialchars($exam['course_description'] ?? 'No description available.')) ?>
</p>

<ul>
    <li>Duration: <strong><?= (int)$exam['duration'] ?> minutes</strong></li>
    <li>Total marks: <strong><?= (int)$exam['total_marks'] ?></strong></li>
    <li>Do not refresh the page — answers are autosaved.</li>
</ul>

<form method="post" action="../api/start_attempt.php">
    <input type="hidden" name="exam_id" value="<?= (int)$exam_id ?>">
    <button class="btn btn-primary">Start Exam</button>
    <a href="dashboard.php" class="btn btn-secondary">Back</a>
</form>

</body>
</html>
