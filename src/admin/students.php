<?php
require_once '../middleware/auth.php';
requireRole('admin');
require_once '../config/db.php';

/* Programs */
$programs = [];
try {
    $programsStmt = $pdo->query("SELECT id, name FROM programs ORDER BY name ASC");
    $programs = $programsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

/* Students */
$stmt = $pdo->query("
    SELECT 
        u.id,
        u.full_name,
        u.email,
        u.country,
        u.program,
        u.status,
        u.created_at
    FROM users u
    WHERE u.role = 'student'
    ORDER BY u.created_at DESC
");
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php require '../constants/header.php' ?>
<title>Manage Students</title>
<style>
    .form-control:focus {
        border-color: #eab32e !important;
        box-shadow: 0 0 0 .2rem rgba(234,179,46,0.25) !important;
        outline: none;
    }

    .form-control:focus-visible {
        outline: 2px solid rgba(234,179,46,0.35);
        outline-offset: 2px;
    }
</style>
</head>
<body class="body">

<div class="container-fluid">
<div class="row">
<?php require 'sidebar.php' ?>

<main class="col-12 col-md-9 col-lg-10 p-4">
<h2>Manage Students</h2>
<p class="text-muted">View and control student access</p>

<?php if (!empty($_SESSION['flash'])): ?>
<div class="alert alert-info">
<?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?>
</div>
<?php endif; ?>

<div class="table-responsive">
<table class="table table-bordered table-hover align-middle">
<thead class="table-light">
<tr>
<th>Name</th>
<th>Email</th>
<th>Country</th>
<th>Program</th>
<th>Status</th>
<th>Registered</th>
<th class="text-center">Actions</th>
</tr>
</thead>

<tbody>
<?php foreach ($students as $s): ?>
<tr>
<td><?= htmlspecialchars($s['full_name']) ?></td>
<td><?= htmlspecialchars($s['email']) ?></td>
<td><?= htmlspecialchars($s['country']) ?></td>
<td><?= htmlspecialchars($s['program'] ?? '—') ?></td>

<td>
<span class="badge bg-<?= $s['status'] === 'active' ? 'success' : 'danger' ?>">
<?= ucfirst($s['status']) ?>
</span>
</td>

<td><?= date('M d, Y', strtotime($s['created_at'])) ?></td>


<td class="text-center position-relative">
<div class="dropdown">
<button class="btn btn-sm btn-light border rounded-circle"
        data-bs-toggle="dropdown">
<i class="bi bi-three-dots-vertical"></i>
</button>

<ul class="dropdown-menu dropdown-menu-end shadow"
    style="z-index:3000;">

    <li>
    <button class="dropdown-item change-program-btn"
            data-bs-toggle="modal"
            data-bs-target="#changeProgramModal"
            data-student-id="<?= $s['id'] ?>"
            data-student-name="<?= htmlspecialchars($s['full_name'], ENT_QUOTES) ?>"
            data-current-program="<?= htmlspecialchars($s['program'] ?? '', ENT_QUOTES) ?>">
    <i class="bi bi-person-lines-fill me-2"></i>Change Program
    </button>
    </li>

    <li>
    <a class="dropdown-item"
    href="student_attempts.php?id=<?= $s['id'] ?>">
    <i class="bi bi-journal-text me-2"></i>Attempts
    </a>
    </li>

    <li>
    <a class="dropdown-item"
    href="student_toggle.php?id=<?= $s['id'] ?>">
    <i class="bi <?= $s['status']==='active'?'bi-person-x-fill text-warning':'bi-person-check-fill text-success' ?> me-2"></i>
    <?= $s['status']==='active'?'Block':'Activate' ?>
    </a>
    </li>

    <li><hr class="dropdown-divider"></li>

    <li>
    <a class="dropdown-item text-danger"
    href="student_delete.php?id=<?= $s['id'] ?>"
    onclick="return confirm('Delete this student permanently?')">
    <i class="bi bi-trash me-2"></i>Delete
    </a>
    </li>
</ul>
</div>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</main>
</div>
</div>

<!-- Change Program Modal -->
<div class="modal fade" id="changeProgramModal" tabindex="-1">
<div class="modal-dialog modal-dialog-centered">
<div class="modal-content">

<form method="POST" action="student_update_program.php" id="changeProgramForm" novalidate>
<div class="modal-header">
<h5 class="modal-title">Change Student Program</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body">
<input class="form-control" type="hidden" name="student_id" id="modal_student_id">

<div class="mb-3">
<label class="form-label">Student</label>
<div id="modal_student_name" class="fw-semibold"></div>
</div>

<div class="mb-3">
<label class="form-label">Program</label>
<select name="program" id="modal_program" class="form-select form-control" required>
<option value="">Select program</option>
<?php foreach ($programs as $p): ?>
<option value="<?= htmlspecialchars($p['name']) ?>">
<?= htmlspecialchars($p['name']) ?>
</option>
<?php endforeach; ?>
</select>
<div class="invalid-feedback">Please select a program.</div>
</div>
</div>

<div class="modal-footer">
<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
<button type="submit" class="btn btn-primary">Save</button>
</div>
</form>

</div>
</div>
</div>

<script src="../../public/assets/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.querySelectorAll('.change-program-btn').forEach(btn => {
btn.addEventListener('click', () => {
document.getElementById('modal_student_id').value = btn.dataset.studentId;
document.getElementById('modal_student_name').textContent = btn.dataset.studentName;

const select = document.getElementById('modal_program');
select.value = btn.dataset.currentProgram || '';
});
});

(() => {
const form = document.getElementById('changeProgramForm');
form.addEventListener('submit', e => {
if (!form.checkValidity()) {
e.preventDefault();
e.stopPropagation();
}
form.classList.add('was-validated');
});
})();
</script>

</body>
</html>
