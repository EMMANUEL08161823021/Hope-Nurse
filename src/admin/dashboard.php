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
    SELECT e.id, e.duration, e.total_marks, e.num_questions, e.status, e.created_at,
           e.program_id, e.course_id,
           p.name AS program_name,
           c.title AS course_title
    FROM exams e
    LEFT JOIN programs p ON e.program_id = p.id
    LEFT JOIN courses c ON e.course_id = c.id
    ORDER BY e.created_at DESC
    LIMIT 50
");
$recentExams = $recentExamsStmt->fetchAll(PDO::FETCH_ASSOC);

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

<div class="container-fluid">
  <div class="row">

    <!-- SIDEBAR -->
    <?php require 'sidebar.php'?>

    <!-- MAIN -->
    <main class="col-12 col-md-9 col-lg-10 p-4">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
          <h2 class="mb-0">Admin Dashboard</h2>
          <p class="text-muted small mb-0">Exam Management Overview</p>
        </div>

        <div class="d-flex gap-2">
          <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createExamModal">
              + Create Exam
          </button>
        </div>
      </div>

      <!-- METRICS -->
      <div class="row mb-4">
          <div class="col-sm-6 col-md-3 mb-3">
              <div class="card text-center shadow-sm">
                  <div class="card-body">
                      <h6>Total Exams</h6>
                      <h3><?= (int)$totalExams ?></h3>
                  </div>
              </div>
          </div>

          <div class="col-sm-6 col-md-3 mb-3">
              <div class="card text-center shadow-sm">
                  <div class="card-body">
                      <h6>Active Exams</h6>
                      <h3><?= (int)$activeExams ?></h3>
                  </div>
              </div>
          </div>

          <div class="col-sm-6 col-md-3 mb-3">
              <div class="card text-center shadow-sm">
                  <div class="card-body">
                      <h6>Total Students</h6>
                      <h3><?= (int)$totalStudents ?></h3>
                  </div>
              </div>
          </div>

          <div class="col-sm-6 col-md-3 mb-3">
              <div class="card text-center shadow-sm">
                  <div class="card-body">
                      <h6>Exam Attempts</h6>
                      <h3><?= (int)$totalAttempts ?></h3>
                  </div>
              </div>
          </div>
      </div>

      <!-- Toast -->
      <div class="position-relative">
          <div id="toastContainer" class="toast-container position-static mt-0 mb-3">
              <?php if ($toastMessage): ?>
                  <div id="examToast" class="toast align-items-center text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
                      <div class="d-flex">
                          <div class="toast-body">
                              <?= htmlspecialchars($toastMessage) ?>
                          </div>
                          <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                      </div>
                  </div>
                  <?php unset($_SESSION['flash']); ?>
              <?php endif; ?>
          </div>
      </div>

      <!-- RECENT EXAMS TABLE -->
      <div id="examsSection" class="card mb-4">
        <div class="card-body">
          <h4 class="mb-3">Recent Exams</h4>

          <div class="table-responsive">
          <table class="table table-bordered mt-2" id="recentExamsTable">
              <thead>
                  <tr>
                      <th>Program / Course</th>
                      <th>Questions</th>
                      <th>Duration</th>
                      <th>Total Marks</th>
                      <th>Status</th>
                      <th>Created</th>
                      <th>Action</th>
                  </tr>
              </thead>
              <tbody>
                  <?php if (count($recentExams) === 0): ?>
                      <tr>
                          <td colspan="6" class="text-center">No exams found</td>
                      </tr>
                  <?php else: ?>
                      <?php foreach ($recentExams as $exam): ?>
                          <tr>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($exam['program_name'] ?? '—') ?></div>
                                    <div class="small text-muted"><?= htmlspecialchars($exam['course_title'] ?? '—') ?></div>
                                </td>

                                <td><?= (int)($exam['num_questions'] ?? 0) ?></td>
                                <td><?= (int)($exam['duration'] ?? 0) ?> min</td>

                                <td><?= (int)($exam['total_marks'] ?? 0) ?></td>

                                <td>
                                    <span class="badge bg-<?= 
                                        ($exam['status'] === 'in_progress') ? 'success' :
                                        (($exam['status'] === 'closed') ? 'danger' : 'secondary')
                                    ?>">
                                        <?= htmlspecialchars(ucfirst($exam['status'] ?? '')) ?>
                                    </span>
                                </td>

                                <td><?= htmlspecialchars($exam['created_at'] ?? '') ?></td>

                                <td class="text-nowrap">
                                    <!-- View exam details -->
                                    <a href="exams_view.php?id=<?= (int)$exam['id'] ?>"
                                        class="btn btn-sm btn-outline-primary"
                                        role="button"
                                        data-bs-toggle="tooltip"
                                        title="View details"
                                        aria-label="View exam details">
                                        <i class="bi bi-eye" aria-hidden="true"></i>
                                    </a>

                                    <!-- Results -->
                                    <a href="exam_results.php?exam_id=<?= (int)$exam['id'] ?>"
                                        class="btn btn-sm btn-info"
                                        role="button"
                                        data-bs-toggle="tooltip"
                                        title="View results"
                                        aria-label="View exam results">
                                        <i class="bi bi-clipboard-check" aria-hidden="true"></i>
                                    </a>

                                    <!-- Delete (keeps your existing protection) -->
                                    <?php if ($exam['status'] !== 'in_progress'): ?>
                                        <a href="exam_delete.php?id=<?= (int)$exam['id'] ?>"
                                        class="btn btn-sm btn-danger"
                                        onclick="return confirm('Delete this exam permanently?')"
                                        data-bs-toggle="tooltip"
                                        title="Delete"
                                        aria-label="Delete exam">
                                        <i class="bi bi-trash" aria-hidden="true"></i>
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-secondary" disabled
                                                title="Cannot delete an exam in progress"
                                                aria-label="Cannot delete an exam in progress"
                                                aria-disabled="true">
                                        <i class="bi bi-trash" aria-hidden="true"></i>
                                        </button>
                                    <?php endif; ?>

                                    <!-- Edit: embed minimal data attributes needed for edit modal -->
                                    <button
                                        class="btn btn-sm btn-warning edit-exam-btn"
                                        data-id="<?= $exam['id'] ?>"
                                        data-program="<?= $exam['program_id'] ?>"
                                        data-course="<?= $exam['course_id'] ?>"
                                        data-duration="<?= $exam['duration'] ?>"
                                        data-total="<?= $exam['total_marks'] ?>"
                                        data-questions="<?= $exam['num_questions'] ?>"
                                        data-status="<?= $exam['status'] ?>"
                                    >
                                        Edit
                                    </button>
                                </td>
                          </tr>
                      <?php endforeach; ?>
                  <?php endif; ?>
              </tbody>
          </table>
          </div>
        </div>
      </div>

    </main>
  </div>
</div>

<!-- Create Exam Modal (with suggested duration logic) -->
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

          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="num_questions" class="form-label">Number of questions</label>
              <input id="num_questions" name="num_questions" type="number" class="form-control" min="1" value="20" required>
              <div class="invalid-feedback">Enter the number of questions (min 1).</div>
            </div>

            <div class="col-md-6 mb-3">
              <label for="exam_type" class="form-label">Exam type</label>
              <select id="exam_type" name="exam_type" class="form-select" required>
                <option value="standard" selected>Standard MCQ (recall & application)</option>
                <option value="clinical">Clinical / Case-heavy</option>
              </select>
              <div class="invalid-feedback">Please select an exam type.</div>
            </div>
          </div>

          <div class="row gx-3">
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

          <!-- Suggested time range UI -->
          <div id="durationSuggestion" class="mb-3" style="display:none;">
            <small class="text-muted">Suggested duration: <strong id="suggestedRangeText">—</strong></small>
            <div class="mt-2">
              <button type="button" id="applyMin" class="btn btn-sm btn-outline-primary me-1">Use Min</button>
              <button type="button" id="applyMedian" class="btn btn-sm btn-outline-secondary me-1">Use Median</button>
              <button type="button" id="applyMax" class="btn btn-sm btn-outline-success">Use Max</button>
              <small class="d-block mt-2 text-muted">You may still edit the duration manually.</small>
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
          <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Create Exam</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Exam Modal (unchanged markup) -->
<div class="modal fade" id="editExamModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
    <form method="POST" action="edit_exam.php" id="editExamForm" class="needs-validation" novalidate>
        <input type="hidden" name="exam_id" id="edit_exam_id">

        <div class="modal-header">
        <h5 class="modal-title">Edit Exam</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
        <div class="mb-3">
            <label for="edit_program_id" class="form-label">Program</label>
            <select id="edit_program_id" name="program_id" class="form-select" required>
            <option value="">Select program</option>
            <?php foreach ($programs as $p): ?>
                <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
            <?php endforeach; ?>
            </select>
            <div class="invalid-feedback">Please choose a program.</div>
        </div>

        <div class="mb-3">
            <label for="edit_course_id" class="form-label">Course</label>
            <select id="edit_course_id" name="course_id" class="form-select" required disabled>
            <option value="">Select a program first</option>
            </select>
            <div class="invalid-feedback">Please choose a course.</div>
        </div>

        <div class="row">
            <div class="col-md-4 mb-3">
                <label for="edit_duration" class="form-label">Duration (minutes)</label>
                <input id="edit_duration" type="number" name="duration" class="form-control" min="1" required>
            </div>

            <div class="col-md-4 mb-3">
                <label for="edit_total" class="form-label">Total Marks</label>
                <input id="edit_total" type="number" name="total_marks" class="form-control" min="0" required>
            </div>

            <div class="col-md-4 mb-3">
                <label for="edit_num_questions" class="form-label">Questions</label>
                <input id="edit_num_questions" type="number" name="num_questions" class="form-control" min="1" required>
            </div>
        </div>


        <div class="mb-3">
            <label for="edit_status" class="form-label">Status</label>
            <select id="edit_status" name="status" class="form-select" required>
            <option value="draft">Draft</option>
            <option value="in_progress">In Progress</option>
            <option value="closed">Closed</option>
            </select>
            <div class="invalid-feedback">Please select a status.</div>
        </div>
        </div>

        <div class="modal-footer">
        <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-warning" type="submit">Save Changes</button>
        </div>

    </form>
    </div>
  </div>
</div>

<!-- Bootstrap JS: adjust path if your assets live elsewhere -->
<script src="../../public/assets/dist/js/bootstrap.bundle.min.js"></script>

<!-- Script: suggested-duration logic -->
<script>
(function () {
  // Configurable defaults (nursing-friendly)
  const CONFIG = {
    standard: {
      perQuestion: { low: 1.25, high: 2.0 },    // minutes per question
      reading: { low: 8, high: 12 },            // instruction/reading time minutes
      buffer: { low: 0.05, high: 0.12 }         // fraction (5% - 12%)
    },
    clinical: {
      perQuestion: { low: 1.5, high: 2.5 },
      reading: { low: 10, high: 15 },
      buffer: { low: 0.10, high: 0.15 }
    }
  };

  // DOM elements
  const numQEl = document.getElementById('num_questions');
  const examTypeEl = document.getElementById('exam_type');
  const suggestedContainer = document.getElementById('durationSuggestion');
  const suggestedText = document.getElementById('suggestedRangeText');
  const durationEl = document.getElementById('duration');
  const applyMinBtn = document.getElementById('applyMin');
  const applyMedianBtn = document.getElementById('applyMedian');
  const applyMaxBtn = document.getElementById('applyMax');

  function clampToInt(v) { return Math.max(1, Math.ceil(Number(v) || 0)); }

  function computeRange(nQuestions, type) {
    // ensure integers
    nQuestions = Math.max(0, Number(nQuestions) || 0);
    const cfg = CONFIG[type] || CONFIG.standard;

    const rawMin = (nQuestions * cfg.perQuestion.low) + cfg.reading.low;
    const rawMax = (nQuestions * cfg.perQuestion.high) + cfg.reading.high;

    const minWithBuffer = Math.ceil(rawMin * (1 + cfg.buffer.low));
    const maxWithBuffer = Math.ceil(rawMax * (1 + cfg.buffer.high));

    // ensure sensible lower bound
    return {
      min: Math.max(1, minWithBuffer),
      max: Math.max(minWithBuffer, maxWithBuffer)
    };
  }

  function updateSuggestionDisplay() {
    const n = Number(numQEl.value);
    if (!n || n < 1) {
      suggestedContainer.style.display = 'none';
      suggestedText.textContent = '—';
      return;
    }
    const type = examTypeEl.value;
    const range = computeRange(n, type);

    // median as midpoint rounded
    const median = Math.ceil((range.min + range.max) / 2);

    suggestedText.textContent = `${range.min} – ${range.max} minutes (median ${median} min)`;
    suggestedContainer.style.display = 'block';

    // set data attributes for apply buttons
    applyMinBtn.dataset.value = range.min;
    applyMedianBtn.dataset.value = median;
    applyMaxBtn.dataset.value = range.max;
  }

  // apply handlers
  applyMinBtn.addEventListener('click', () => {
    durationEl.value = clampToInt(applyMinBtn.dataset.value);
    durationEl.focus();
  });
  applyMedianBtn.addEventListener('click', () => {
    durationEl.value = clampToInt(applyMedianBtn.dataset.value);
    durationEl.focus();
  });
  applyMaxBtn.addEventListener('click', () => {
    durationEl.value = clampToInt(applyMaxBtn.dataset.value);
    durationEl.focus();
  });

  // update live as admin edits
  numQEl.addEventListener('input', updateSuggestionDisplay);
  examTypeEl.addEventListener('change', updateSuggestionDisplay);

  // initialize on modal open (use Bootstrap modal events)
  const createExamModalEl = document.getElementById('createExamModal');
  createExamModalEl.addEventListener('shown.bs.modal', function () {
    // pre-fill duration if empty by applying median suggestion
    updateSuggestionDisplay();
    if (!durationEl.value) {
      // apply median suggestion if available
      const median = applyMedianBtn.dataset.value;
      if (median) durationEl.value = clampToInt(median);
    }
  });

  // Basic client-side form validation (Bootstrap)
  (function () {
    const form = document.getElementById('createExamForm');
    form.addEventListener('submit', function (event) {
      if (!form.checkValidity()) {
        event.preventDefault();
        event.stopPropagation();
      }
      form.classList.add('was-validated');
    }, false);
  })();

  // Run an initial update (in case modal content is in DOM already)
  updateSuggestionDisplay();
})();
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

document.querySelectorAll('.edit-exam-btn').forEach(button => {
    button.addEventListener('click', () => {
        document.getElementById('edit_exam_id').value = button.dataset.id;
        document.getElementById('edit_program_id').value = button.dataset.program;
        document.getElementById('edit_duration').value = button.dataset.duration;
        document.getElementById('edit_total').value = button.dataset.total;
        document.getElementById('edit_num_questions').value = button.dataset.questions;
        document.getElementById('edit_status').value = button.dataset.status;

        // Enable course dropdown
        const courseSelect = document.getElementById('edit_course_id');
        courseSelect.disabled = false;
        courseSelect.innerHTML = `<option value="${button.dataset.course}" selected>Current course</option>`;

        new bootstrap.Modal(document.getElementById('editExamModal')).show();
    });
});

(function () {
    const GET_COURSES_URL = '../admin/get_courses.php'; // adjust relative path if needed

    // helper to populate #edit_course_id from courses array
    function populateCourseSelect(selectEl, courses, selectedId) {
        selectEl.innerHTML = '<option value="">Select course</option>';
        courses.forEach(c => {
            const opt = document.createElement('option');
            opt.value = c.id;
            opt.textContent = c.title;
            if (String(c.id) === String(selectedId)) opt.selected = true;
            selectEl.appendChild(opt);
        });
        selectEl.disabled = false;
    }

    async function fetchCourses(programId) {
        if (!programId) return [];
        try {
            const resp = await fetch(`${GET_COURSES_URL}?program_id=${encodeURIComponent(programId)}`, { credentials: 'same-origin' });
            const payload = await resp.json();
            if (payload && payload.success) return payload.data || [];
        } catch (e) {
            console.error('fetchCourses error', e);
        }
        return [];
    }

    // When Edit button is clicked: populate the edit modal fields
    document.querySelectorAll('.edit-exam-btn').forEach(btn => {
        btn.addEventListener('click', async function () {
            const id = this.dataset.id || '';
            const programId = this.dataset.program_id || '';
            const courseId = this.dataset.course_id || '';
            const duration = this.dataset.duration || '';
            const total = this.dataset.total || '';
            const status = this.dataset.status || 'draft';

            // set hidden id
            document.getElementById('edit_exam_id').value = id;

            // set simple fields
            document.getElementById('edit_duration').value = duration;
            document.getElementById('edit_total').value = total;
            document.getElementById('edit_status').value = status;

            // set program select
            const editProgramSelect = document.getElementById('edit_program_id');
            editProgramSelect.value = programId;

            // load courses for this program and select the right one
            const editCourseSelect = document.getElementById('edit_course_id');
            editCourseSelect.disabled = true;
            editCourseSelect.innerHTML = '<option>Loading courses…</option>';

            const courses = await fetchCourses(programId);
            if (courses.length === 0) {
                editCourseSelect.innerHTML = '<option value="">No courses</option>';
                editCourseSelect.disabled = true;
            } else {
                populateCourseSelect(editCourseSelect, courses, courseId);
            }
        });
    });

    // If admin changes program inside edit modal, reload courses
    const editProgram = document.getElementById('edit_program_id');
    if (editProgram) {
        editProgram.addEventListener('change', async function () {
            const pid = this.value;
            const editCourseSelect = document.getElementById('edit_course_id');
            if (!pid) {
                editCourseSelect.innerHTML = '<option value="">Select a program first</option>';
                editCourseSelect.disabled = true;
                return;
            }
            editCourseSelect.disabled = true;
            editCourseSelect.innerHTML = '<option>Loading courses…</option>';
            const courses = await fetchCourses(pid);
            if (courses.length === 0) {
                editCourseSelect.innerHTML = '<option value="">No courses</option>';
                editCourseSelect.disabled = true;
            } else {
                populateCourseSelect(editCourseSelect, courses, '');
            }
        });
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

  // Focus first input when modal is shown
  const createModal = document.getElementById('createExamModal');
  if (createModal) {
    createModal.addEventListener('shown.bs.modal', function () {
      const programInput = document.getElementById('program_id');
      if (programInput) programInput.focus();
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
