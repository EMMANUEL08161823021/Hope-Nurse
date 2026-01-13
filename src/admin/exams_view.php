<?php
require_once '../middleware/auth.php';
requireRole('admin');
require_once '../config/db.php';

$exam_id = (int)($_GET['id'] ?? 0);
if ($exam_id <= 0) {
    die('Invalid exam id');
}

// ==== HANDLE ADD QUESTION SUBMIT (modal posts here) ====
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_question'])) {
    try {
        // Get and validate incoming values
        $post_exam_id = (int)($_POST['exam_id'] ?? 0);
        $question_text = trim($_POST['question_text'] ?? '');
        $type = $_POST['question_type'] ?? '';
        $marks = (int)($_POST['marks'] ?? 0);

        if ($post_exam_id <= 0) throw new Exception('Invalid exam specified.');
        if ($question_text === '') throw new Exception('Question text is required.');
        if ($marks <= 0) throw new Exception('Marks must be greater than zero.');

        // Load exam and ensure it's draft and has total_marks configured
        $chkExam = $pdo->prepare("SELECT id, total_marks, status FROM exams WHERE id = ? LIMIT 1");
        $chkExam->execute([$post_exam_id]);
        $examCheck = $chkExam->fetch(PDO::FETCH_ASSOC);
        if (!$examCheck) throw new Exception('Exam not found.');
        if ($examCheck['status'] !== 'draft') throw new Exception('Cannot add questions unless exam is in draft status.');
        $examTotalMarks = (int)($examCheck['total_marks'] ?? 0);
        if ($examTotalMarks <= 0) throw new Exception('Exam total marks is not configured. Set total marks before adding questions.');

        // Compute existing marks sum
        $sumStmt = $pdo->prepare("SELECT COALESCE(SUM(marks),0) FROM questions WHERE exam_id = ?");
        $sumStmt->execute([$post_exam_id]);
        $existingMarks = (int)$sumStmt->fetchColumn();
        if ($existingMarks >= $examTotalMarks) {
            throw new Exception('Cannot add more questions — exam total marks already reached (' . $examTotalMarks . ').');
        }
        $remaining = $examTotalMarks - $existingMarks;
        if ($marks > $remaining) {
            throw new Exception("This question ({$marks}) would exceed the exam total. Remaining marks: {$remaining}.");
        }

        // Validate options / correct answers depending on type (server-side)
        if (in_array($type, ['single_choice', 'multiple_choice'], true)) {
            $options = $_POST['options'] ?? [];
            // trim and keep non-empty options
            $cleanOptions = [];
            foreach ($options as $opt) {
                $t = trim((string)$opt);
                if ($t !== '') $cleanOptions[] = $t;
            }
            if (count($cleanOptions) < 2) throw new Exception('At least two non-empty options are required.');
            // correct[] expected (checkbox hidden trick ensures presence)
            $correct = $_POST['correct'] ?? [];
            if (!is_array($correct)) $correct = [$correct];
            if ($type === 'single_choice' && count($correct) !== 1) {
                throw new Exception('Single choice requires exactly one correct option.');
            }
            if ($type === 'multiple_choice' && count($correct) < 1) {
                throw new Exception('Select at least one correct option for multiple choice.');
            }
        }

        if ($type === 'true_false') {
            $correct_tf = $_POST['correct_tf'] ?? '';
            if ($correct_tf !== 'True' && $correct_tf !== 'False') {
                throw new Exception('True/False must have a correct answer selected.');
            }
        }

        if (in_array($type, ['short_answer', 'fill_blank'], true)) {
            $correct_answer = trim($_POST['correct_answer'] ?? '');
            if ($correct_answer === '') throw new Exception('Correct answer is required for text questions.');
        }

        // Passed all checks -> insert question + options in transaction
        $pdo->beginTransaction();

        $insQ = $pdo->prepare("INSERT INTO questions (exam_id, question_text, question_type, marks) VALUES (?, ?, ?, ?)");
        $insQ->execute([$post_exam_id, $question_text, $type, $marks]);
        $question_id = (int)$pdo->lastInsertId();

        // Insert options according to type into `options` table (exists in your DB)
        if (in_array($type, ['single_choice', 'multiple_choice'], true)) {
            $optionsAll = $_POST['options'] ?? [];
            // we will iterate original options order but skip empties
            foreach ($optionsAll as $idx => $optText) {
                $t = trim((string)$optText);
                if ($t === '') continue;
                // is_correct: check if index was included in correct[]
                $is_correct = 0;
                $correctRaw = $_POST['correct'] ?? [];
                // normalize to strings
                $correctStr = array_map('strval', (array)$correctRaw);
                if (in_array((string)$idx, $correctStr, true)) $is_correct = 1;

                $insOpt = $pdo->prepare("INSERT INTO options (question_id, option_text, is_correct) VALUES (?, ?, ?)");
                $insOpt->execute([$question_id, $t, $is_correct]);
            }
        } elseif ($type === 'true_false') {
            foreach (['True', 'False'] as $val) {
                $is_correct = ($_POST['correct_tf'] === $val) ? 1 : 0;
                $insOpt = $pdo->prepare("INSERT INTO options (question_id, option_text, is_correct) VALUES (?, ?, ?)");
                $insOpt->execute([$question_id, $val, $is_correct]);
            }
        } elseif (in_array($type, ['short_answer','fill_blank'], true)) {
            $insOpt = $pdo->prepare("INSERT INTO options (question_id, option_text, is_correct) VALUES (?, ?, 1)");
            $insOpt->execute([$question_id, $correct_answer]);
        }

        $pdo->commit();

        // success -> set flash and PRG redirect to avoid resubmits
        $_SESSION['flash'] = 'Question added successfully.';
        header('Location: exams_view.php?id=' . $post_exam_id);
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $errors[] = $e->getMessage();
        // keep page load and re-open modal with errors
    }
}

// ==== END POST HANDLER ====

// Fetch exam + admin name (fresh)
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

    <?php if (!empty($_SESSION['flash'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_SESSION['flash']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>

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

            <!-- ACTIONS -->
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

                <!-- Add Question button -->
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

<!-- Add Question Modal (posts to this same file) -->
<div class="modal fade" id="addQuestionModal" tabindex="-1" aria-labelledby="addQuestionModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form id="addQuestionForm" method="POST" class="needs-validation" novalidate>
        <input type="hidden" name="exam_id" value="<?= $exam_id ?>">
        <input type="hidden" name="add_question" value="1">
        <div class="modal-header">
          <h5 class="modal-title" id="addQuestionModalLabel">Add Question</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">

            <div id="add-errors" class="mb-3">
                <?php if ($errors): ?>
                    <div class="alert alert-danger">
                        <?php foreach ($errors as $err): ?>
                            <div><?= htmlspecialchars($err) ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <label for="question_text" class="form-label">Question</label>
                <textarea id="question_text" name="question_text" class="form-control" required><?= htmlspecialchars($_POST['question_text'] ?? '') ?></textarea>
                <div class="invalid-feedback">Enter the question text.</div>
            </div>

            <div class="mb-3">
                <label for="question_type" class="form-label">Question Type</label>
                <select name="question_type" id="question_type" class="form-select" required onchange="toggleFields()">
                    <option value="">Select</option>
                    <option value="single_choice" <?= (($_POST['question_type'] ?? '') === 'single_choice') ? 'selected' : '' ?>>Multiple Choice (Single)</option>
                    <option value="multiple_choice" <?= (($_POST['question_type'] ?? '') === 'multiple_choice') ? 'selected' : '' ?>>Select All That Apply</option>
                    <option value="true_false" <?= (($_POST['question_type'] ?? '') === 'true_false') ? 'selected' : '' ?>>True / False</option>
                    <option value="short_answer" <?= (($_POST['question_type'] ?? '') === 'short_answer') ? 'selected' : '' ?>>Short Answer</option>
                    <option value="fill_blank" <?= (($_POST['question_type'] ?? '') === 'fill_blank') ? 'selected' : '' ?>>Fill in the Blank</option>
                </select>
                <div class="invalid-feedback">Select a question type.</div>
            </div>

            <div class="mb-3">
                <label for="marks" class="form-label">Marks</label>
                <input id="marks" type="number" name="marks" class="form-control" min="1" required value="<?= htmlspecialchars($_POST['marks'] ?? '') ?>">
                <div class="invalid-feedback">Enter marks (> 0).</div>
                <div class="form-text">Exam total marks: <strong><?= htmlspecialchars($exam['total_marks'] ?? 'N/A') ?></strong></div>
            </div>

            <!-- Options box -->
            <div id="optionsBox" style="display:none;">
                <h6>Options</h6>
                <div id="optionsList">
                    <?php
                    // If form errored and options were posted, repopulate them
                    $postedOptions = $_POST['options'] ?? [];
                    $baseline = max(4, count($postedOptions));
                    for ($i=0; $i<$baseline; $i++): 
                        $val = $postedOptions[$i] ?? '';
                        ?>
                    <div class="input-group mb-2 option-row">
                        <input type="text" name="options[]" class="form-control" placeholder="Option text" value="<?= htmlspecialchars($val) ?>">
                        <span class="input-group-text correct-wrap">
                            <input type="checkbox" name="correct[]" value="<?= $i ?>" <?= (in_array((string)$i, array_map('strval', (array)($_POST['correct'] ?? []))) ? 'checked' : '') ?>>
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
                    <option value="True" <?= (($_POST['correct_tf'] ?? '') === 'True') ? 'selected' : '' ?>>True</option>
                    <option value="False" <?= (($_POST['correct_tf'] ?? '') === 'False') ? 'selected' : '' ?>>False</option>
                </select>
            </div>

            <!-- Text answer -->
            <div id="answerBox" style="display:none;">
                <label class="form-label">Correct Answer</label>
                <input type="text" name="correct_answer" id="correct_answer" class="form-control" value="<?= htmlspecialchars($_POST['correct_answer'] ?? '') ?>">
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
            // hidden checkbox to preserve server friendly correct[] array
            const hidden = document.createElement('input');
            hidden.type = 'checkbox';
            hidden.name = 'correct[]';
            hidden.value = idx;
            hidden.style.display = 'none';
            r.addEventListener('change', function() {
                document.querySelectorAll('input[name="correct[]"]').forEach(cb => cb.checked = false);
                if (r.checked) hidden.checked = true;
            });
            // preserve checked state from server if present
            if (document.querySelector('input[name="correct[]"][value="'+idx+'"]')?.checked) {
                r.checked = true;
                hidden.checked = true;
            }
            wrap.appendChild(r);
            wrap.appendChild(hidden);
        } else if (type === 'multiple_choice') {
            const cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.name = 'correct[]';
            cb.value = idx;
            cb.className = 'form-check-input';
            // preserve checked state from server if present
            if (document.querySelector('input[name="correct[]"][value="'+idx+'"]')?.checked) cb.checked = true;
            wrap.appendChild(cb);
        } else {
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
    toggleFields();
}

function removeOption() {
    const list = document.getElementById('optionsList');
    const rows = list.querySelectorAll('.option-row');
    if (rows.length <= 2) return;
    rows[rows.length - 1].remove();
    toggleFields();
}

/* Client-side form validation and small UX */
(function () {
  'use strict'
  const form = document.getElementById('addQuestionForm');
  if (!form) return;

  form.addEventListener('submit', function (event) {
    if (!form.checkValidity()) {
      event.preventDefault();
      event.stopPropagation();
      form.classList.add('was-validated');
      return;
    }

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

        const checked = Array.from(document.querySelectorAll('#optionsList input[name="correct[]"]')).some(cb => cb.checked);
        if (!checked) {
            event.preventDefault();
            event.stopPropagation();
            showAddError('Mark at least one correct option.');
            return;
        }

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

/* Initialize modal UI state on show; if server-side errors exist, open modal */
const addModalEl = document.getElementById('addQuestionModal');
if (addModalEl) {
    addModalEl.addEventListener('show.bs.modal', function () {
        const form = document.getElementById('addQuestionForm');
        form.classList.remove('was-validated');
        document.getElementById('add-errors').innerHTML = '';
        // rebuild minimal 4 option rows only if empty
        const list = document.getElementById('optionsList');
        if (!list.querySelector('.option-row')) {
            for (let i=0;i<4;i++) {
                const row = document.createElement('div');
                row.className = 'input-group mb-2 option-row';
                row.innerHTML = `<input type="text" name="options[]" class="form-control" placeholder="Option text">
                                 <span class="input-group-text correct-wrap"><input type="checkbox" name="correct[]" value="${i}"></span>`;
                list.appendChild(row);
            }
        }
        toggleFields();
    });

    // If server returned errors (PHP $errors), auto-show modal
    <?php if (!empty($errors)): ?>
    var modal = new bootstrap.Modal(addModalEl);
    modal.show();
    <?php endif; ?>
}
</script>

</body>
</html>
