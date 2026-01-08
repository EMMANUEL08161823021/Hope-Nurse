<?php
require_once '../middleware/auth.php';
requireRole('admin');
require_once '../config/db.php';

// Fetch students with their latest submitted score (if any)
$stmt = $pdo->query("
    SELECT 
        u.id,
        u.full_name,
        u.email,
        u.status,
        u.created_at,
        (
            SELECT ea.score
            FROM attempts ea
            WHERE ea.student_id = u.id
              AND ea.status IN ('submitted','auto_submitted')
            ORDER BY ea.submitted_at DESC
            LIMIT 1
        ) AS latest_score
    FROM users u
    WHERE u.role = 'student'
    ORDER BY u.created_at DESC
");
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
            <?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?>
        </div>
    <?php endif; ?>

    <table class="table table-bordered table-hover">
        <thead class="table-light">
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Score</th>
                <th>Status</th>
                <th>Registered</th>
                <th width="30%">Actions</th>
            </tr>
        </thead>
        <tbody>

        <?php if (count($students) === 0): ?>
            <tr>
                <td colspan="6" class="text-center">No students found</td>
            </tr>
        <?php else: ?>
            <?php foreach ($students as $s): ?>
                <tr>
                    <td><?= htmlspecialchars($s['full_name']) ?></td>
                    <td><?= htmlspecialchars($s['email']) ?></td>

                    <td>
                        <?php
                        // latest_score might be NULL
                        if ($s['latest_score'] === null) {
                            echo '<span class="text-muted">—</span>';
                        } else {
                            // format numeric score nicely
                            echo htmlspecialchars((string)$s['latest_score']);
                        }
                        ?>
                    </td>

                    <td>
                        <span class="badge bg-<?= $s['status'] === 'active' ? 'success' : 'danger' ?>">
                            <?= htmlspecialchars(ucfirst($s['status'])) ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($s['created_at']) ?></td>
                    <td>
                        <!-- Toggle status -->
                        <a href="student_toggle.php?id=<?= urlencode($s['id']) ?>" 
                           class="btn btn-sm btn-warning">
                           <?= $s['status'] === 'active' ? 'Block' : 'Activate' ?>
                        </a>

                        <!-- View attempts -->
                        <a href="student_attempts.php?id=<?= urlencode($s['id']) ?>" 
                           class="btn btn-sm btn-primary">
                           Attempts
                        </a>

                        <!-- Delete -->
                        <a href="student_delete.php?id=<?= urlencode($s['id']) ?>"
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
