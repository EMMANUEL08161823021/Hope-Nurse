<?php
require_once '../middleware/auth.php';
requireRole('admin');
require_once '../config/db.php';

$exam_id = (int)($_GET['id'] ?? 0);
if ($exam_id <= 0) {
    die('Invalid exam id');
}

// Fetch exam + admin name
$stmt = $pdo->prepare("
    SELECT exams.*, users.full_name AS admin_name
    FROM exams
    JOIN users ON exams.created_by = users.id
    WHERE exams.id = ?
    LIMIT 1
");
$stmt->execute([$exam_id]);
$exam = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$exam) die("Exam not found");

// Questions
$qStmt = $pdo->prepare("SELECT * FROM questions WHERE exam_id = ? ORDER BY id ASC");
$qStmt->execute([$exam_id]);
$questions = $qStmt->fetchAll(PDO::FETCH_ASSOC);

// Counts
$qCountStmt = $pdo->prepare("SELECT COUNT(*) FROM questions WHERE exam_id = ?");
$qCountStmt->execute([$exam_id]);
$totalQuestions = (int)$qCountStmt->fetchColumn();

$attemptsStmt = $pdo->prepare("SELECT COUNT(*) FROM attempts WHERE exam_id = ?");
$attemptsStmt->execute([$exam_id]);
$attemptCount = (int)$attemptsStmt->fetchColumn();
?>
<?php require '../constants/header.php' ?>
    <title>View Exam</title>
</head>
<body>

<div class="container mt-4">

    <a href="dashboard.php" class="btn btn-secondary mb-3">← Back to Exams</a>

    <div class="card shadow-sm">
        <div class="card-body">

            <h3><?= htmlspecialchars($exam['title']) ?></h3>
            <p class="text-muted"><?= htmlspecialchars($exam['description'] ?? 'No description') ?></p>

            <hr>

            <div class="row mb-3">
                <div class="col-md-3">
                    <strong>Status</strong><br>
                    <span class="badge bg-<?= 
                        $exam['status'] === 'in_progress' ? 'success' :
                        ($exam['status'] === 'closed' ? 'danger' : 'secondary')
                    ?>">
                        <?= htmlspecialchars(ucfirst($exam['status'])) ?>
                    </span>
                </div>

                <div class="col-md-3">
                    <strong>Created By</strong><br>
                    <?= htmlspecialchars($exam['admin_name']) ?>
                </div>

                <div class="col-md-3">
                    <strong>Created On</strong><br>
                    <?= htmlspecialchars($exam['created_at']) ?>
                </div>

                <div class="col-md-3">
                    <strong>Attempts</strong><br>
                    <?= $attemptCount ?>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-4">
                    <strong>Total Questions:</strong><br>
                    <?= $totalQuestions ?>
                </div>

                <div class="col-md-4">
                    <strong>Duration:</strong><br>
                    <?= htmlspecialchars($exam['duration'] ?? 'N/A') ?> minutes
                </div>

                <div class="col-md-4">
                    <strong>Total Marks:</strong><br>
                    <?= htmlspecialchars($exam['total_marks'] ?? 'Auto-calculated') ?>
                </div>
            </div>

            <hr>

            <!-- ACTIONS: Manage Questions | Start / Close | Delete (if safe) | Add Question (modal) -->
            <div class="d-flex gap-2 mb-3">

                <a href="questions.php?exam_id=<?= $exam_id ?>" class="btn btn-primary">
                    Manage Questions
                </a>

                <a href="exam_toggle.php?id=<?= $exam_id ?>&action=start"
                   class="btn btn-success">Start Exam</a>

                <a href="exam_toggle.php?id=<?= $exam_id ?>&action=close"
                   class="btn btn-danger">Close Exam</a>

                <?php if ($exam['status'] !== 'in_progress'): ?>
                    <?php if ($attemptCount === 0): ?>
                        <a href="exam_delete.php?id=<?= $exam_id ?>"
                           class="btn btn-outline-danger"
                           onclick="return confirm('Delete this exam and all its questions? This cannot be undone.')">
                           Delete Exam
                        </a>
                    <?php else: ?>
                        <button class="btn btn-outline-secondary" disabled
                                title="Cannot delete: students have attempted this exam">
                            Delete Exam
                        </button>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- Add Question button: only when exam is draft -->
                <?php if ($exam['status'] === 'draft'): ?>
                    <button class="btn btn-success ms-auto" data-bs-toggle="modal" data-bs-target="#addQuestionModal">
                        + Add Question
                    </button>
                <?php else: ?>
                    <button class="btn btn-success ms-auto" disabled title="Cannot add questions once exam is started">
                        + Add Question
                    </button>
                <?php endif; ?>

            </div>

            <div class="mt-4">
                <h4>Questions</h4>

                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Question</th>
                            <th>Type</th>
                            <th>Marks</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($questions)): ?>
                            <tr><td colspan="4" class="text-center">No questions yet</td></tr>
                        <?php else: ?>
                            <?php foreach ($questions as $q): ?>
                                <tr>
                                    <td><?= htmlspecialchars($q['question_text']) ?></td>
                                    <td><?= htmlspecialchars(ucfirst(str_replace('_',' ', $q['question_type']))) ?></td>
                                    <td><?= (int)$q['marks'] ?></td>
                                    <td>
                                        <a href="delete_question.php?id=<?= (int)$q['id'] ?>&exam_id=<?= $exam_id ?>"
                                           class="btn btn-danger btn-sm"
                                           onclick="return confirm('Delete question?')">
                                           Delete
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>

</div>

<!-- Add Question Modal -->
<div class="modal fade" id="addQuestionModal" tabindex="-1" aria-labelledby="addQuestionModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <!-- Posting to add_question.php (server file that handles insertion) -->
      <form id="addQuestionForm" action="add_question.php" method="POST" class="needs-validation" novalidate>
        <input type="hidden" name="exam_id" value="<?= $exam_id ?>">
        <div class="modal-header">
          <h5 class="modal-title" id="addQuestionModalLabel">Add Question</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">

            <div id="add-errors" class="mb-3"></div>

            <div class="mb-3">
                <label for="question_text" class="form-label">Question</label>
                <textarea id="question_text" name="question_text" class="form-control" required></textarea>
                <div class="invalid-feedback">Enter the question text.</div>
            </div>

            <div class="mb-3">
                <label for="question_type" class="form-label">Question Type</label>
                <select name="question_type" id="question_type" class="form-select" required onchange="toggleFields()">
                    <option value="">Select</option>
                    <option value="single_choice">Multiple Choice (Single)</option>
                    <option value="multiple_choice">Select All That Apply</option>
                    <option value="true_false">True / False</option>
                    <option value="short_answer">Short Answer</option>
                    <option value="fill_blank">Fill in the Blank</option>
                </select>
                <div class="invalid-feedback">Select a question type.</div>
            </div>

            <div class="mb-3">
                <label for="marks" class="form-label">Marks</label>
                <input id="marks" type="number" name="marks" class="form-control" min="1" required>
                <div class="invalid-feedback">Enter marks (> 0).</div>
                <div class="form-text">Exam total marks: <strong><?= htmlspecialchars($exam['total_marks'] ?? 'N/A') ?></strong></div>
            </div>

            <!-- Options box -->
            <div id="optionsBox" style="display:none;">
                <h6>Options</h6>
                <div id="optionsList">
                    <?php for ($i=0; $i<4; $i++): ?>
                    <div class="input-group mb-2 option-row">
                        <input type="text" name="options[]" class="form-control" placeholder="Option text">
                        <span class="input-group-text correct-wrap">
                            <!-- placeholder for correct input (checkbox or radio) -->
                            <input type="checkbox" name="correct[]" value="<?= $i ?>">
                        </span>
                    </div>
                    <?php endfor; ?>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="addOption()">Add option</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="removeOption()">Remove option</button>
                    <div class="form-text ms-auto">For single choice use radio (only one correct). For multiple choice use checkboxes.</div>
                </div>
            </div>

            <!-- True/False box -->
            <div id="trueFalseBox" style="display:none;">
                <label class="form-label">Correct Answer</label>
                <select name="correct_tf" id="correct_tf" class="form-select">
                    <option value="">Select</option>
                    <option value="True">True</option>
                    <option value="False">False</option>
                </select>
            </div>

            <!-- Text answer -->
            <div id="answerBox" style="display:none;">
                <label class="form-label">Correct Answer</label>
                <input type="text" name="correct_answer" id="correct_answer" class="form-control">
            </div>

        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success">Save Question</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Bootstrap JS -->
<script src="../../public/assets/dist/js/bootstrap.bundle.min.js"></script>

<script>
/* Toggle fields and ensure correct input types are appropriate */
function toggleFields() {
    const type = document.getElementById('question_type').value;
    const optionsBox = document.getElementById('optionsBox');
    const trueFalseBox = document.getElementById('trueFalseBox');
    const answerBox = document.getElementById('answerBox');

    optionsBox.style.display = ['single_choice','multiple_choice'].includes(type) ? 'block' : 'none';
    trueFalseBox.style.display = type === 'true_false' ? 'block' : 'none';
    answerBox.style.display = ['short_answer','fill_blank'].includes(type) ? 'block' : 'none';

    // adjust correct input types inside optionsList
    const optionRows = document.querySelectorAll('#optionsList .option-row');
    optionRows.forEach((row, idx) => {
        const wrap = row.querySelector('.correct-wrap');
        wrap.innerHTML = '';
        if (type === 'single_choice') {
            const r = document.createElement('input');
            r.type = 'radio';
            r.name = 'correct_single';
            r.value = idx;
            r.className = 'form-check-input';
            // keep a fallback field so server still receives correct[] if needed:
            // we'll also add a hidden checkbox that server can read as correct[] if present.
            const hidden = document.createElement('input');
            hidden.type = 'checkbox';
            hidden.name = 'correct[]';
            hidden.value = idx;
            hidden.style.display = 'none';
            // When radio changes, update hidden checkbox states
            r.addEventListener('change', function() {
                // clear all hidden checkboxes
                document.querySelectorAll('input[name="correct[]"]').forEach(cb => cb.checked = false);
                if (r.checked) hidden.checked = true;
            });
            wrap.appendChild(r);
            wrap.appendChild(hidden);
        } else if (type === 'multiple_choice') {
            const cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.name = 'correct[]';
            cb.value = idx;
            cb.className = 'form-check-input';
            wrap.appendChild(cb);
        } else {
            // default: no correct option UI
            wrap.innerHTML = '';
        }
    });
}

/* Add / remove option rows */
function addOption() {
    const list = document.getElementById('optionsList');
    const idx = list.querySelectorAll('.option-row').length;
    const div = document.createElement('div');
    div.className = 'input-group mb-2 option-row';
    div.innerHTML = `
        <input type="text" name="options[]" class="form-control" placeholder="Option text">
        <span class="input-group-text correct-wrap">
            <input type="checkbox" name="correct[]" value="${idx}">
        </span>
    `;
    list.appendChild(div);
    // re-run toggleFields to set correct input types according to current question_type
    toggleFields();
}

function removeOption() {
    const list = document.getElementById('optionsList');
    const rows = list.querySelectorAll('.option-row');
    if (rows.length <= 2) return; // keep at least 2 options
    rows[rows.length - 1].remove();
    toggleFields();
}

/* Client-side form validation */
(function () {
  'use strict'
  const form = document.getElementById('addQuestionForm');
  if (!form) return;

  form.addEventListener('submit', function (event) {
    // Allow default HTML5 validation first
    if (!form.checkValidity()) {
      event.preventDefault();
      event.stopPropagation();
      form.classList.add('was-validated');
      return;
    }

    // Custom validation for options when required
    const type = document.getElementById('question_type').value;
    if (['single_choice','multiple_choice'].includes(type)) {
        const optionInputs = document.querySelectorAll('#optionsList input[name="options[]"]');
        let nonEmpty = 0;
        optionInputs.forEach(i => { if (i.value.trim() !== '') nonEmpty++; });
        if (nonEmpty < 2) {
            event.preventDefault();
            event.stopPropagation();
            showAddError('At least two non-empty options are required.');
            return;
        }

        // ensure at least one correct selected
        const checked = Array.from(document.querySelectorAll('#optionsList input[name="correct[]"]')).some(cb => cb.checked);
        if (!checked) {
            event.preventDefault();
            event.stopPropagation();
            showAddError('Mark at least one correct option.');
            return;
        }

        // if single_choice ensure exactly one
        if (type === 'single_choice') {
            const checkedCount = Array.from(document.querySelectorAll('#optionsList input[name="correct[]"]')).filter(cb => cb.checked).length;
            if (checkedCount !== 1) {
                event.preventDefault();
                event.stopPropagation();
                showAddError('Single choice requires exactly one correct option.');
                return;
            }
        }
    }

    if (type === 'true_false') {
        const tf = document.getElementById('correct_tf').value;
        if (!tf) {
            event.preventDefault();
            event.stopPropagation();
            showAddError('Select True or False.');
            return;
        }
    }

    form.classList.add('was-validated');
  }, false);

  function showAddError(msg) {
    const el = document.getElementById('add-errors');
    el.innerHTML = '<div class="alert alert-danger">'+msg+'</div>';
    window.scrollTo({top: 0, behavior: 'smooth'});
  }
})();

/* Ensure options UI is initialized when modal opens */
const addModalEl = document.getElementById('addQuestionModal');
if (addModalEl) {
    addModalEl.addEventListener('show.bs.modal', function () {
        // reset form
        const form = document.getElementById('addQuestionForm');
        form.classList.remove('was-validated');
        form.reset();
        document.getElementById('add-errors').innerHTML = '';
        // rebuild optionsList to 4 rows baseline
        const list = document.getElementById('optionsList');
        list.innerHTML = '';
        for (let i=0;i<4;i++) {
            const row = document.createElement('div');
            row.className = 'input-group mb-2 option-row';
            row.innerHTML = `<input type="text" name="options[]" class="form-control" placeholder="Option text">
                             <span class="input-group-text correct-wrap"><input type="checkbox" name="correct[]" value="${i}"></span>`;
            list.appendChild(row);
        }
        // set toggle fields for default state
        toggleFields();
    });
}
</script>

</body>
</html>
