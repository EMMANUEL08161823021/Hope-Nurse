<?php
require_once __DIR__ . '/../middleware/auth.php';
requireRole('admin');

require_once '../config/db.php';

/* ===== METRICS ===== */

// Total exams
$totalExams = $pdo->query("SELECT COUNT(*) FROM exams")->fetchColumn();

// Active exams
$activeExams = $pdo->query("SELECT COUNT(*) FROM exams WHERE status='in_progress'")->fetchColumn();

// Total students
$totalStudents = $pdo->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();

// Total questions
$totalQuestions = $pdo->query("SELECT COUNT(*) FROM questions")->fetchColumn();

// Exam attempts (optional table)
try {
    $totalAttempts = $pdo->query("SELECT COUNT(*) FROM attempts")->fetchColumn();
} catch (Exception $e) {
    $totalAttempts = 0;
}

// Recent exams
$recentExamsStmt = $pdo->query("
    SELECT id, title, description, duration, total_marks, status, created_at 
    FROM exams 
    ORDER BY created_at DESC 
    LIMIT 5
");

$recentExams = $recentExamsStmt->fetchAll();

// Grab flash (if any) for toast
$toastMessage = null;
if (!empty($_SESSION['flash'])) {
    $toastMessage = (string)$_SESSION['flash'];
    // We'll unset after rendering so it doesn't reappear
}

try {
    $programsStmt = $pdo->query("SELECT id, name FROM programs ORDER BY name ASC");
    $programs = $programsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $programs = [];
}

?>



<?php require '../constants/header.php'?>
<title>Admin Dashboard</title>
</head>
<body>

<div class="container mt-4">
    <h2>Admin Dashboard</h2>
    <p class="text-muted">Exam Management Overview</p>

    <!-- METRICS -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-center shadow-sm">
                <div class="card-body">
                    <h6>Total Exams</h6>
                    <h3><?= (int)$totalExams ?></h3>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card text-center shadow-sm">
                <div class="card-body">
                    <h6>Active Exams</h6>
                    <h3><?= (int)$activeExams ?></h3>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card text-center shadow-sm">
                <div class="card-body">
                    <h6>Total Students</h6>
                    <h3><?= (int)$totalStudents ?></h3>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card text-center shadow-sm">
                <div class="card-body">
                    <h6>Total Questions</h6>
                    <h3><?= (int)$totalQuestions ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- OPTIONAL ATTEMPTS -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-center shadow-sm">
                <div class="card-body">
                    <h6>Exam Attempts</h6>
                    <h3><?= (int)$totalAttempts ?></h3>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between">
        <h4>Recent Exams</h4>

        <!-- QUICK ACTIONS -->
        <div class="d-flex gap-2 align-items-center">
            <a href="students.php" class="btn btn-secondary">Manage Students</a>
            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createExamModal">
                + Create Exam
            </button>
        </div>
    </div>


    <!-- RECENT EXAMS -->

    <!-- Toast area (appears below the recent exams table) -->
    <div class="position-relative">
        <div id="toastContainer" class="toast-container position-static mt-3">
            <?php if ($toastMessage): ?>
                <div id="examToast" class="toast align-items-center text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="d-flex">
                        <div class="toast-body">
                            <?= htmlspecialchars($toastMessage) ?>
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                </div>
                <?php
                // clear the flash so it doesn't appear again
                unset($_SESSION['flash']);
                ?>
            <?php endif; ?>
        </div>
    </div>
    <table class="table table-bordered mt-2" id="recentExamsTable">
        <thead>
            <tr>
                <th>Title</th>
                <th>Status</th>
                <th>Created</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($recentExams) === 0): ?>
                <tr>
                    <td colspan="4" class="text-center">No exams found</td>
                </tr>
            <?php else: ?>
                <?php foreach ($recentExams as $exam): ?>
                    <tr>
                        <td><?= htmlspecialchars($exam['title']) ?></td>
                        <td>
                            <span class="badge bg-<?= 
                                $exam['status'] === 'in_progress' ? 'success' :
                                ($exam['status'] === 'closed' ? 'danger' : 'secondary')
                            ?>">
                                <?= htmlspecialchars(ucfirst($exam['status'])) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($exam['created_at']) ?></td>
                        <td>
                            <!-- View exam details -->
                            <a href="exams_view.php?id=<?= (int)$exam['id'] ?>" class="btn btn-sm btn-outline-primary">
                                View
                            </a>

                            <!-- RESULTS: show students who attempted this exam -->
            
                            <a href="exam_results.php?exam_id=<?= (int)$exam['id'] ?>" class="btn btn-sm btn-info">Results</a>


                            <?php if ($exam['status'] !== 'in_progress'): ?>
                                <a href="exam_delete.php?id=<?= (int)$exam['id'] ?>"
                                class="btn btn-sm btn-danger"
                                onclick="return confirm('Delete this exam permanently?')">
                                    Delete
                                </a>
                            <?php else: ?>
                                <button class="btn btn-sm btn-secondary" disabled title="Cannot delete an exam in progress">Delete</button>
                            <?php endif; ?>

                            <button class="btn btn-warning btn-sm edit-exam-btn"
                                    data-bs-toggle="modal"
                                    data-bs-target="#editExamModal"
                                    data-id="<?= (int)$exam['id'] ?>"
                                    data-title="<?= htmlspecialchars($exam['title'], ENT_QUOTES) ?>"
                                    data-description="<?= htmlspecialchars($exam['description'] ?? '', ENT_QUOTES) ?>"
                                    data-duration="<?= (int)$exam['duration'] ?>"
                                    data-total="<?= (int)$exam['total_marks'] ?>">
                                Edit Exam
                            </button>
                        </td>

                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>


    <a href="../auth/logout.php" class="btn btn-outline-danger mt-4">Logout</a>
</div>



<!-- Create Exam Modal -->
<div class="modal fade" id="createExamModal" tabindex="-1" aria-labelledby="createExamModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
        <form id="createExamForm" action="store_exam.php" method="POST" class="needs-validation" novalidate>
        <div class="modal-header">
            <h5 class="modal-title" id="createExamModalLabel">Create Exam</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
            <div class="mb-3">
                <label for="program_id" class="form-label">Program</label>
                <select id="program_id" name="program_id" class="form-select" required>
                    <option value="">Select program</option>
                    <?php foreach ($programs as $p): ?>
                        <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="invalid-feedback">Please select a program for this exam.</div>
            </div>

            <!-- COURSE: will be populated when a program is selected -->
            <div class="mb-3">
                <label for="course_id" class="form-label">Course</label>
                <select id="course_id" name="course_id" class="form-select" required disabled>
                    <option value="">Select a program first</option>
                </select>
                <div class="invalid-feedback">Please select a course for this exam.</div>
            </div>

            <div class="mb-3">
                <label for="title" class="form-label">Exam Title</label>
                <input id="title" type="text" name="title" class="form-control" required>
                <div class="invalid-feedback">Please enter an exam title.</div>
            </div>

            <div class="mb-3">
                <label for="description" class="form-label">Description</label>
                <textarea id="description" name="description" class="form-control" rows="3"></textarea>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="duration" class="form-label">Duration (minutes)</label>
                    <input id="duration" type="number" name="duration" class="form-control" min="1" required>
                    <div class="invalid-feedback">Enter duration in minutes (min 1).</div>
                </div>

                <div class="col-md-6 mb-3">
                    <label for="total_marks" class="form-label">Total Marks</label>
                    <input id="total_marks" type="number" name="total_marks" class="form-control" min="0" required>
                    <div class="invalid-feedback">Enter total marks for this exam.</div>
                </div>
            </div>

            <div class="mb-3">
                <label for="status" class="form-label">Status</label>
                <select id="status" name="status" class="form-select" required>
                    <option value="draft" selected>Draft</option>
                    <option value="in_progress">In Progress</option>
                    <option value="closed">Closed</option>
                </select>
                <div class="invalid-feedback">Please select a status.</div>
            </div>

        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Create Exam</button>
        </div>
        </form>
    </div>
  </div>
</div>

<!-- Edit Exam Modal -->
<div class="modal fade" id="editExamModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">

      <form method="POST" action="edit_exam.php" class="needs-validation" novalidate>
        <input type="hidden" name="exam_id" id="edit_exam_id">

        <div class="modal-header">
          <h5 class="modal-title">Edit Exam</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">

          <div class="mb-3">
            <label class="form-label">Exam Title</label>
            <input type="text" id="edit_title" name="title" class="form-control" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea id="edit_description" name="description" class="form-control"></textarea>
          </div>

          <div class="mb-3">
            <label class="form-label">Duration (minutes)</label>
            <input type="number" id="edit_duration" name="duration" class="form-control" min="1" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Total Marks</label>
            <input type="number" id="edit_total" name="total_marks" class="form-control" min="0" required>
          </div>

        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-warning" type="submit">Save Changes</button>
        </div>

      </form>

    </div>
  </div>
</div>



<!-- Bootstrap JS: adjust path if your assets live elsewhere -->
<script src="../../public/assets/dist/js/bootstrap.bundle.min.js"></script>


<script>
document.querySelectorAll('.edit-exam-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('edit_exam_id').value = btn.dataset.id;
        document.getElementById('edit_title').value = btn.dataset.title;
        document.getElementById('edit_description').value = btn.dataset.description;
        document.getElementById('edit_duration').value = btn.dataset.duration;
        document.getElementById('edit_total').value = btn.dataset.total;
    });
});
</script>

<script>
    (function () {
  const GET_COURSES_URL = '../admin/get_courses.php'; // adjust path if needed
  const programSelect = document.getElementById('program_id');
  const courseSelect = document.getElementById('course_id');
  const form = document.getElementById('createExamForm');

  function setCourseLoading(loading, message) {
    courseSelect.disabled = loading;
    courseSelect.innerHTML = loading ? `<option>Loading courses…</option>` : (message ? `<option value="">${message}</option>` : `<option value="">Select course</option>`);
  }

  async function loadCourses(programId) {
    if (!programId) {
      setCourseLoading(false, 'Select a program first');
      courseSelect.required = false;
      return;
    }

    setCourseLoading(true);

    try {
      const resp = await fetch(`${GET_COURSES_URL}?program_id=${encodeURIComponent(programId)}`, {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' }
      });

      const payload = await resp.json();

      if (!payload.success || !Array.isArray(payload.data) || payload.data.length === 0) {
        setCourseLoading(false, 'No courses available');
        courseSelect.required = false;
        courseSelect.disabled = true;
        return;
      }

      // populate options
      courseSelect.innerHTML = '<option value="">Select course</option>';
      payload.data.forEach(c => {
        const opt = document.createElement('option');
        opt.value = c.id;
        opt.textContent = c.title;
        courseSelect.appendChild(opt);
      });

      courseSelect.disabled = false;
      courseSelect.required = true;
    } catch (err) {
      console.error('Failed to load courses', err);
      setCourseLoading(false, 'Unable to load');
      courseSelect.required = false;
      courseSelect.disabled = true;
    }
  }

  if (programSelect) {
    programSelect.addEventListener('change', function () {
      loadCourses(this.value);
    });

    // If modal reopens and program already selected, preload courses
    if (programSelect.value) {
      loadCourses(programSelect.value);
    }
  }

  // Integrate with Bootstrap validation; also ensure a course is chosen if required
  if (form) {
    form.addEventListener('submit', function (event) {
      // Native validation
      if (!form.checkValidity()) {
        event.preventDefault();
        event.stopPropagation();
        form.classList.add('was-validated');
        return;
      }

      // Additional check: if courseSelect is required but no value chosen, prevent submit
      if (!courseSelect.disabled && courseSelect.required && !courseSelect.value) {
        event.preventDefault();
        event.stopPropagation();
        courseSelect.classList.add('is-invalid');
        // remove is-invalid once user changes selection
        courseSelect.addEventListener('change', function handler() {
          if (courseSelect.value) {
            courseSelect.classList.remove('is-invalid');
            courseSelect.removeEventListener('change', handler);
          }
        });
        form.classList.add('was-validated');
        return;
      }

      // allow submit
      form.classList.add('was-validated');
    }, false);
  }
})();
</script>

<script>
(function () {
  'use strict';

  // Client-side validation for the modal form
  const form = document.getElementById('createExamForm');
  if (form) {
    form.addEventListener('submit', function (event) {
      if (!form.checkValidity()) {
        event.preventDefault();
        event.stopPropagation();
      }
      form.classList.add('was-validated');
    }, false);
  }

  // Focus title input when modal is shown
  const createModal = document.getElementById('createExamModal');
  if (createModal) {
    createModal.addEventListener('shown.bs.modal', function () {
      const titleInput = document.getElementById('title');
      if (titleInput) titleInput.focus();
    });
  }

  // If a toast exists on the page, show it (this displays the "Exam created" message)
  const toastEl = document.getElementById('examToast');
  if (toastEl) {
    const toast = new bootstrap.Toast(toastEl, { delay: 4000 });
    toast.show();
  }

})();
</script>

</body>
</html>
