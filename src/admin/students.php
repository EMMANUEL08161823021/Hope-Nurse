<?php
require_once '../middleware/auth.php';
requireRole('admin');
require_once '../config/db.php';

// Fetch students only
$stmt = $pdo->query("
    SELECT id, full_name, email, status, created_at 
    FROM users 
    WHERE role = 'student'
    ORDER BY created_at DESC
");
$students = $stmt->fetchAll();
?>

<?php require '../constants/header.php'?>

    <title>Manage Students</title>
</head>
<body>

<div class="container mt-4">
    <h2>Manage Students</h2>
    <p class="text-muted">View and control student access</p>

    <?php if (!empty($_SESSION['flash'])): ?>
        <div class="alert alert-info">
            <?= $_SESSION['flash']; unset($_SESSION['flash']); ?>
        </div>
    <?php endif; ?>

    <table class="table table-bordered table-hover">
        <thead class="table-light">
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Status</th>
                <th>Registered</th>
                <th width="30%">Actions</th>
            </tr>
        </thead>
        <tbody>

        <?php if (count($students) === 0): ?>
            <tr>
                <td colspan="5" class="text-center">No students found</td>
            </tr>
        <?php else: ?>
            <?php foreach ($students as $s): ?>
                <tr>
                    <td><?= htmlspecialchars($s['full_name']) ?></td>
                    <td><?= htmlspecialchars($s['email']) ?></td>
                    <td>
                        <span class="badge bg-<?= $s['status'] === 'active' ? 'success' : 'danger' ?>">
                            <?= ucfirst($s['status']) ?>
                        </span>
                    </td>
                    <td><?= $s['created_at'] ?></td>
                    <td>
                        <!-- Toggle status -->
                        <a href="student_toggle.php?id=<?= $s['id'] ?>" 
                           class="btn btn-sm btn-warning">
                           <?= $s['status'] === 'active' ? 'Block' : 'Activate' ?>
                        </a>

                        <!-- View attempts -->
                        <a href="student_attempts.php?id=<?= $s['id'] ?>" 
                           class="btn btn-sm btn-primary">
                           Attempts
                        </a>

                        <!-- Delete -->
                        <a href="student_delete.php?id=<?= $s['id'] ?>"
                           class="btn btn-sm btn-danger"
                           onclick="return confirm('Delete this student permanently?')">
                           Delete
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>

        </tbody>
    </table>

    <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
</div>

</body>
</html>
