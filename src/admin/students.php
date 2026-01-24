<?php
require_once '../middleware/auth.php';
requireRole('admin');
require_once '../config/db.php';

// Fetch list of programs for the select
try {
    $programsStmt = $pdo->query("SELECT id, name FROM programs ORDER BY name ASC");
    $programs = $programsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $programs = [];
}

// Fetch students with their latest submitted score (if any) and current program
$stmt = $pdo->query("
    SELECT 
        u.id,
        u.full_name,
        u.email,
        u.country,
        u.program,
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

<div class="container-fluid">
    <div class="row">
        <?php require 'sidebar.php'?>

        <main class="col-12 col-md-9 col-lg-10 p-4">
            <h2>Manage Students</h2>
            <p class="text-muted">View and control student access</p>

            <?php if (!empty($_SESSION['flash'])): ?>
                <div class="alert alert-info">
                    <?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?>
                </div>
            <?php endif; ?>

            <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Country</th>
                        <th>Program</th>
                        <th>Status</th>
                        <th>Registered</th>
                        <th width="30%">Actions</th>
                    </tr>
                </thead>
                <tbody>

                <?php if (count($students) === 0): ?>
                    <tr>
                        <td colspan="7" class="text-center">No students found</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($students as $s): ?>
                        <tr>
                            <td><?= htmlspecialchars($s['full_name']) ?></td>
                            <td><?= htmlspecialchars($s['email']) ?></td>
                            <td><?= htmlspecialchars($s['country']) ?></td>
                            <td><?= htmlspecialchars($s['program'] ?? '—') ?></td>
                            <td>
                                <span class="badge bg-<?= $s['status'] === 'active' ? 'success' : 'danger' ?>">
                                    <?= htmlspecialchars(ucfirst($s['status'])) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($s['created_at']) ?></td>
                            <td>
                                <!-- Change program (opens modal) -->
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-primary change-program-btn"
                                    data-student-id="<?= (int)$s['id'] ?>"
                                    data-student-name="<?= htmlspecialchars($s['full_name'], ENT_QUOTES) ?>"
                                    data-current-program="<?= htmlspecialchars($s['program'] ?? '', ENT_QUOTES) ?>"
                                    data-bs-toggle="modal"
                                    data-bs-target="#changeProgramModal"
                                >
                                    Change Program
                                </button>

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
            </div>
        </main>
    </div>
</div>

<!-- Change Program Modal -->
<div class="modal fade" id="changeProgramModal" tabindex="-1" aria-labelledby="changeProgramModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="changeProgramForm" method="POST" action="student_update_program.php" class="needs-validation" novalidate>
        <div class="modal-header">
          <h5 class="modal-title" id="changeProgramModalLabel">Change Student Program</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
            <input type="hidden" name="student_id" id="modal_student_id" value="">

            <div class="mb-3">
                <label class="form-label">Student</label>
                <div id="modal_student_name" class="fw-semibold"></div>
            </div>

            <div class="mb-3">
                <label for="modal_program" class="form-label">Program</label>
                <select id="modal_program" name="program" class="form-select" required>
                    <option value="">Select program</option>
                    <?php foreach ($programs as $p): ?>
                        <option value="<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>"><?= htmlspecialchars($p['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="invalid-feedback">Please select a program.</div>
            </div>

            <div class="mb-0">
                <small class="text-muted">Changing a student's program will assign them to the selected curriculum. Make sure this is the intended action.</small>
            </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Change</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Bootstrap JS (adjust path if needed) -->
<script src="../../public/assets/dist/js/bootstrap.bundle.min.js"></script>

<script>
(function () {
    'use strict';

    // populate modal when change-program button clicked
    document.querySelectorAll('.change-program-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const studentId = this.dataset.studentId || '';
            const studentName = this.dataset.studentName || '';
            const currentProgram = this.dataset.currentProgram || '';

            document.getElementById('modal_student_id').value = studentId;
            document.getElementById('modal_student_name').textContent = studentName;

            const programSelect = document.getElementById('modal_program');
            // try to set current program (match by value)
            let matched = false;
            for (let i = 0; i < programSelect.options.length; i++) {
                if (programSelect.options[i].value === currentProgram) {
                    programSelect.selectedIndex = i;
                    matched = true;
                    break;
                }
            }
            if (!matched) {
                // clear selection
                programSelect.selectedIndex = 0;
            }
        });
    });

    // client-side validation for modal form
    const frm = document.getElementById('changeProgramForm');
    if (frm) {
        frm.addEventListener('submit', function (e) {
            if (!frm.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            }
            frm.classList.add('was-validated');
        }, false);
    }
})();
</script>

</body>
</html>
