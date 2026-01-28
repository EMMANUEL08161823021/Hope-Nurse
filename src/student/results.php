<?php
require_once '../middleware/auth.php';
requireRole('student');
require_once '../config/db.php';

$student_id = (int)($_SESSION['user']['id'] ?? 0);

if ($student_id <= 0) {
    die('Invalid student session.');
}


$stmt = $pdo->prepare("
    SELECT 
        a.id AS attempt_id,
        a.score,
        a.status AS attempt_status,
        a.submitted_at,
        a.created_at,
        c.title AS title,
        e.total_marks
    FROM attempts a
    JOIN exams e ON a.exam_id = e.id
    JOIN courses c ON e.course_id = c.id
    WHERE a.student_id = ?
      AND e.results_released = 1
    ORDER BY a.created_at DESC
");


$stmt->execute([$student_id]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<?php require '../constants/header.php'?>
    <title>My Results</title>
</head>
<body class="container-fluid body">
    <div class="row">
        <?php require 'sidebar.php'?>
    
        <main class="col-12 col-md-9 col-lg-10 p-4">
            <div class="mb-3" >
    
                <h3>My Course Results</h3>
            
            
                <?php if (empty($results)): ?>
                    <div class="alert alert-info">
                        Results have not been released yet.
                    </div>
                <?php else: ?>
            
            
                <table class="table table-bordered table-striped">
                    <thead class="table-light">
                        <tr>
                            <th>Course / Exam</th>
                            <th>Score</th>
                            <th>Status</th>
                            <th>Date Taken</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $r): ?>
                            <tr>
                                <td><?= htmlspecialchars($r['title']) ?></td>
            
                                <td>
                                    <?= (int)$r['score'] ?>
                                    <?php if (!empty($r['total_marks'])): ?>
                                        / <?= (int)$r['total_marks'] ?>
                                    <?php endif; ?>
                                </td>
            
                                <td>
                                    <span class="badge bg-<?= 
                                        $r['attempt_status'] === 'completed' ? 'success' : 'secondary'
                                    ?>">
                                        <?= htmlspecialchars(ucfirst($r['attempt_status'])) ?>
                                    </span>
                                </td>
            
                                <td>
                                    <?= htmlspecialchars($r['submitted_at'] ?? $r['created_at']) ?>
                                </td>
            
                                <td>
                                    <a href="result.php?attempt_id=<?= (int)$r['attempt_id'] ?>"
                                    class="btn btn-sm btn-outline-primary">
                                        View Result
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            
                <?php endif; ?>
            </div>
            
        </div>
    </div>

</body>
</html>
