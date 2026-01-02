<?php
require_once  '../middleware/auth.php';
requireRole('student');

require_once '../config/db.php';

$studentId = $_SESSION['user']['id'];

$stmt = $pdo->prepare("
    SELECT e.*
    FROM exams e
    WHERE e.status = 'in_progress'
      AND e.id NOT IN (
          SELECT exam_id
          FROM attempts
          WHERE student_id = ?
            AND status IN ('submitted','auto_submitted')
      )
    ORDER BY e.created_at DESC
");

$stmt->execute([$studentId]);
$exams = $stmt->fetchAll();
?>

<?php require '../constants/header.php'?>
     <title>Available Exams</title>
</head>
<body class="container mt-4">

     <h3>Available Exams</h3>

     <?php if (empty($exams)): ?>
     <div class="alert alert-info">
          No available exams at the moment.
     </div>
     <?php else: ?>
     <table class="table table-bordered">
          <thead>
               <tr>
                    <th>Title</th>
                    <th>Description</th>
                    <th>Duration (mins)</th>
                    <th>Total Marks</th>
                    <th>Action</th>
               </tr>
          </thead>
          <tbody>
          <?php foreach ($exams as $exam): ?>
               <tr>
                    <td><?= htmlspecialchars($exam['title']) ?></td>
                    <td><?= htmlspecialchars($exam['description']) ?></td>
                    <td><?= (int)$exam['duration'] ?></td>
                    <td><?= (int)$exam['total_marks'] ?></td>
                    <td>
                         <a href="instructions.php?exam_id=<?= $exam['id'] ?>"
                         class="btn btn-sm btn-primary">
                         Start Exam
                         </a>
                    </td>
               </tr>
          <?php endforeach; ?>
          </tbody>
     </table>
     <?php endif; ?>

</body>
</html>
