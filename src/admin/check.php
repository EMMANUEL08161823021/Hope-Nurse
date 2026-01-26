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

        // Insert options according to type into `options` table
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


// Fetch exam + admin name + course info (defensive joins)
$stmt = $pdo->prepare("\n    SELECT\n        e.*,\n        u.full_name AS admin_name,\n        c.title AS course_title,\n        c.description AS course_description,\n        p.name AS program_name\n    FROM exams e\n    LEFT JOIN users u ON e.created_by = u.id\n    LEFT JOIN courses c ON e.course_id = c.id\n    LEFT JOIN programs p ON e.program_id = p.id\n    WHERE e.id = ?\n    LIMIT 1\n");
$stmt->execute([$exam_id]);
$exam = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$exam) {
    die("Exam not found");
}

// Questions
$qStmt = $pdo->prepare("SELECT * FROM questions WHERE exam_id = ? ORDER BY id ASC");
$qStmt->execute([$exam_id]);
$questions = $qStmt->fetchAll(PDO::FETCH_ASSOC);

// Counts: use single count execution (avoid double fetchColumn misuse)
$qCountStmt = $pdo->prepare("SELECT COUNT(*) FROM questions WHERE exam_id = ?");
$qCountStmt->execute([$exam_id]);
$createdQuestions = (int)$qCountStmt->fetchColumn();
$totalQuestions = $createdQuestions; // keep for backward compatibility
$expectedQuestions = (int)($exam['num_questions'] ?? 0);

$attemptsStmt = $pdo->prepare("SELECT COUNT(*) FROM attempts WHERE exam_id = ?");
$attemptsStmt->execute([$exam_id]);
$attemptCount = (int)$attemptsStmt->fetchColumn();



// ==== HANDLE EDIT EXAM ====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_exam'])) {
    try {
        $exam_id = (int)$_POST['exam_id'];
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $duration = (int)$_POST['duration'];
        $total_marks = (int)$_POST['total_marks'];

        if ($title === '') throw new Exception('Title is required.');
        if ($duration <= 0) throw new Exception('Duration must be greater than zero.');
        if ($total_marks <= 0) throw new Exception('Total marks must be greater than zero.');

        // Check exam status
        $stmt = $pdo->prepare("SELECT status FROM exams WHERE id = ?");
        $stmt->execute([$exam_id]);
        $status = $stmt->fetchColumn();

        if ($status !== 'draft') {
            throw new Exception('Only draft exams can be edited.');
        }

        // Check question marks sum
        $sumStmt = $pdo->prepare("\n            SELECT COALESCE(SUM(marks),0)\n            FROM questions\n            WHERE exam_id = ?\n        ");
        $sumStmt->execute([$exam_id]);
        $questionMarks = (int)$sumStmt->fetchColumn();

       if ($total_marks < $questionMarks) {
            $_SESSION['flash_error'] =
                "Total marks cannot be less than existing question marks ({$questionMarks}).";
            header("Location: exams_view.php?id={$exam_id}");
            exit;
        }

        // Update exam
        $update = $pdo->prepare("\n            UPDATE exams\n            SET title = ?, description = ?, duration = ?, total_marks = ?\n            WHERE id = ?\n        ");
        $update->execute([
            $title,
            $description,
            $duration,
            $total_marks,
            $exam_id
        ]);

        $_SESSION['flash'] = 'Exam updated successfully.';
        header("Location: exams_view.php?id=$exam_id");
        exit;

    } catch (Exception $e) {
        $editErrors[] = $e->getMessage();
    }
}

$optionsByQuestion = [];

$questionIds = array_map(function($q){ return (int)$q['id']; }, $questions ?: []);
if (!empty($questionIds)) {
    // Build placeholders for IN(...)
    $placeholders = implode(',', array_fill(0, count($questionIds), '?'));

    // IMPORTANT: include the option id so client-side edit modal can reference existing rows and update instead of inserting duplicates
    $optSql = "SELECT id, question_id, option_text, is_correct FROM options WHERE question_id IN ($placeholders) ORDER BY id ASC";
    $optStmt = $pdo->prepare($optSql);
    $optStmt->execute($questionIds);
    $allOpts = $optStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($allOpts as $opt) {
        $qid = (int)$opt['question_id'];
        if (!isset($optionsByQuestion[$qid])) $optionsByQuestion[$qid] = [];
        $optionsByQuestion[$qid][] = $opt;
    }
}

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

    <a href="dashboard.php" class="btn btn-secondary mb-3">← Back</a>

    <div class="card shadow-sm">
        <div class="p-4" style="background-color: #042c2c; color: #fff;">
            <h3><?= htmlspecialchars($exam['course_title'] ?? 'Untitled course') ?></h3>
            <p class="text-muted"><?= htmlspecialchars($exam['course_description'] ?? 'No description') ?></p>
        </div>
        <div class="card-body">
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
                    <strong>Questions:</strong><br>
                    <span class="badge bg-success">
                        <?= $createdQuestions ?> / <?= $expectedQuestions ?>
                    </span>
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
                <form method="post" action="exam_toggle.php" class="d-inline-block" onsubmit="return confirmStatusChange(this);">
                    <input type="hidden" name="exam_id" value="<?= (int)$exam_id ?>">
                    <div class="input-group">
                        <select name="status" class="form-select form-select-sm" aria-label="Change status">
                            <option value="draft" <?= $exam['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                            <option value="in_progress" <?= $exam['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                            <option value="closed" <?= $exam['status'] === 'closed' ? 'selected' : '' ?>>Closed</option>
                        </select>
                        <button type="submit" class="btn btn-outline-primary btn-xs">Change</button>
                    </div>
                </form>

                <?php if ($exam['status'] !== 'in_progress'): ?>
                    <?php if ($attemptCount === 0): ?>
                        <a href="exam_delete.php?id=<?= (int)$exam_id ?>"
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
                            <th>Answer(s)</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($questions)): ?>
                            <tr><td colspan="5" class="text-center">No questions yet</td></tr>
                        <?php else: ?>
                            <?php foreach ($questions as $q): 
                                $qid = (int)$q['id'];
                                $qType = $q['question_type'] ?? '';
                                $opts = $optionsByQuestion[$qid] ?? [];
                                // Determine answers presentation depending on question type
                                $answersDisplay = '—';
                                if (in_array($qType, ['single_choice','true_false'], true)) {
                                    // single correct option expected
                                    $found = [];
                                    foreach ($opts as $o) {
                                        if (!empty($o['is_correct']) && (int)$o['is_correct'] === 1) {
                                            $found[] = $o['option_text'];
                                        }
                                    }
                                    $answersDisplay = !empty($found) ? htmlspecialchars(implode(', ', $found)) : '—';
                                } elseif ($qType === 'multiple_choice') {
                                    // possibly multiple correct options
                                    $found = [];
                                    foreach ($opts as $o) {
                                        if (!empty($o['is_correct']) && (int)$o['is_correct'] === 1) {
                                            $found[] = $o['option_text'];
                                        }
                                    }
                                    $answersDisplay = !empty($found) ? htmlspecialchars(implode('; ', $found)) : '—';
                                } else {
                                    // short_answer / fill_blank — show expected_answer if present on question row
                                    if (!empty($q['expected_answer'])) {
                                        $answersDisplay = htmlspecialchars($q['expected_answer']);
                                    } elseif (!empty($opts)) {
                                        // fallback: show all option texts (helpful if your short answers are stored as options)
                                        $texts = array_map(function($o){ return $o['option_text']; }, $opts);
                                        $answersDisplay = htmlspecialchars(implode(', ', $texts));
                                    } else {
                                        $answersDisplay = '—';
                                    }
                                }
                            ?>
                                <tr>
                                    <td><?= nl2br(htmlspecialchars($q['question_text'])) ?></td>
                                    <td><?= htmlspecialchars(ucfirst(str_replace('_',' ', $qType))) ?></td>
                                    <td><?= (int)($q['marks'] ?? 0) ?></td>
                                    <td style="min-width:220px; white-space:normal;"><?= $answersDisplay ?></td>
                                    <td>
                                        <!-- Edit button (opens modal) -->
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-secondary edit-question-btn"
                                            data-qid="<?= (int)$q['id'] ?>"
                                            data-qtext="<?= htmlspecialchars($q['question_text'], ENT_QUOTES) ?>"
                                            data-qtype="<?= htmlspecialchars($q['question_type'], ENT_QUOTES) ?>"
                                            data-qmarks="<?= (int)($q['marks'] ?? 0) ?>"
                                            data-options="<?= htmlspecialchars(json_encode($optionsByQuestion[$q['id']] ?? []), ENT_QUOTES) ?>"
                                            data-bs-toggle="modal"
                                            data-bs-target="#editQuestionModal"
                                        >
                                            <i class="bi bi-pencil"></i> Edit
                                        </button>

                                        <!-- Delete button (keep as before) -->
                                        <a href="delete_question.php?id=<?= (int)$q['id'] ?>&exam_id=<?= urlencode($exam_id) ?>"
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

<!-- Edit Question Modal -->
<div class="modal fade" id="editQuestionModal" tabindex="-1" aria-labelledby="editQuestionModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form id="editQuestionForm" method="POST" action="edit_question.php" class="needs-validation" novalidate>
        <input type="hidden" name="question_id" id="eq_question_id" value="">
        <!-- removed options ids -->
        <div id="eq_removed_ids_container"></div>

        <div class="modal-header">
          <h5 class="modal-title" id="editQuestionModalLabel">Edit Question</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
          <div class="mb-3">
            <label for="eq_question_text" class="form-label">Question</label>
            <textarea id="eq_question_text" name="question_text" class="form-control" rows="3" required></textarea>
            <div class="invalid-feedback">Please enter the question text.</div>
          </div>

          <div class="row gx-2">
            <div class="col-md-4 mb-3">
              <label for="eq_question_type" class="form-label">Type</label>
              <select id="eq_question_type" name="question_type" class="form-select" required>
                <option value="single_choice">Single choice</option>
                <option value="multiple_choice">Multiple choice</option>
                <option value="true_false">True / False</option>
                <option value="short_answer">Short answer</option>
              </select>
              <div class="invalid-feedback">Choose a question type.</div>
            </div>

            <div class="col-md-4 mb-3">
              <label for="eq_marks" class="form-label">Marks</label>
              <input id="eq_marks" name="marks" type="number" class="form-control" min="0" required>
              <div class="invalid-feedback">Enter marks for this question.</div>
            </div>

            <div class="col-md-4 mb-3 d-flex align-items-end">
              <button type="button" id="eq_add_option_btn" class="btn btn-sm btn-outline-primary w-100">
                + Add option
              </button>
            </div>
          </div>

          <!-- Options container -->
          <div id="eq_options_container" class="mb-2">
            <!-- dynamic rows for existing & new options will be injected here -->
          </div>

          <div class="small text-muted">
            For single_choice/true_false choose one correct option. For multiple_choice you may mark multiple options correct.
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-warning">Save changes</button>
        </div>
      </form>
    </div>
  </div>
</div>


<!-- Bootstrap JS -->
<script src="../../public/assets/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
  'use strict';

  // Helpers to create DOM nodes for options
  function createExistingOptionRow(opt) {
    // opt: { id?, question_id, option_text, is_correct }
    const wrapper = document.createElement('div');
    wrapper.className = 'eq-option-row mb-2 d-flex gap-2 align-items-start';
    wrapper.dataset.optId = opt.id ? String(opt.id) : '';

    // text input
    const text = document.createElement('input');
    text.type = 'text';
    text.name = opt.id ? `options_existing[${opt.id}][text]` : `options_new[][text]`;
    text.className = 'form-control';
    text.placeholder = 'Option text';
    text.value = opt.option_text ?? '';
    text.required = true;

    // correct checkbox
    const chkWrapper = document.createElement('div');
    chkWrapper.className = 'form-check ms-2';
    const chk = document.createElement('input');
    chk.type = 'checkbox';
    chk.className = 'form-check-input';
    chk.name = opt.id ? `options_existing[${opt.id}][is_correct]` : `options_new[][is_correct]`;
    chk.value = '1';
    if (opt.is_correct && (opt.is_correct == 1 || opt.is_correct === '1' || opt.is_correct === true)) chk.checked = true;

    const chkLabel = document.createElement('label');
    chkLabel.className = 'form-check-label small';
    chkLabel.textContent = 'Correct';

    chkWrapper.appendChild(chk);
    chkWrapper.appendChild(chkLabel);

    // remove button
    const rmBtn = document.createElement('button');
    rmBtn.type = 'button';
    rmBtn.className = 'btn btn-sm btn-outline-danger';
    rmBtn.innerHTML = '<i class="bi bi-x-lg"></i>';
    rmBtn.title = 'Remove option';

    rmBtn.addEventListener('click', function () {
      // If this is an existing option (has id), add hidden input to removed list
      const existingId = wrapper.dataset.optId;
      if (existingId) {
        const removedContainer = document.getElementById('eq_removed_ids_container');
        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'removed_option_ids[]';
        hidden.value = existingId;
        removedContainer.appendChild(hidden);
      }
      wrapper.remove();
    });

    // assemble
    wrapper.appendChild(text);
    wrapper.appendChild(chkWrapper);
    wrapper.appendChild(rmBtn);

    return wrapper;
  }

  // Add new option row
  document.getElementById('eq_add_option_btn').addEventListener('click', function () {
    const container = document.getElementById('eq_options_container');
    const row = createExistingOptionRow({ option_text: '', is_correct: 0 });
    container.appendChild(row);
    // focus new input
    row.querySelector('input[type="text"]').focus();
  });

  // When edit button clicked: populate modal
  document.querySelectorAll('.edit-question-btn').forEach(btn => {
    btn.addEventListener('click', function () {
      const qid = this.dataset.qid || '';
      const qtext = this.dataset.qtext || '';
      const qtype = this.dataset.qtype || 'single_choice';
      const qmarks = this.dataset.qmarks || 0;
      let opts = [];
      try {
        opts = JSON.parse(this.dataset.options || '[]');
      } catch (e) {
        opts = [];
      }

      // set fields
      document.getElementById('eq_question_id').value = qid;
      document.getElementById('eq_question_text').value = qtext;
      document.getElementById('eq_question_type').value = qtype;
      document.getElementById('eq_marks').value = qmarks;

      // clear removed ids container
      document.getElementById('eq_removed_ids_container').innerHTML = '';

      // populate options container
      const container = document.getElementById('eq_options_container');
      container.innerHTML = '';

      if (Array.isArray(opts) && opts.length) {
        opts.forEach(o => {
          // normalize expected option properties
          const norm = {
            id: o.id ?? o.option_id ?? null,
            option_text: o.option_text ?? o.option ?? '',
            is_correct: o.is_correct ?? o.correct ?? 0
          };
          const r = createExistingOptionRow(norm);
          container.appendChild(r);
        });
      } else {
        // if no options exist, create two empty rows for choice types as starting point
        if (qtype === 'single_choice' || qtype === 'multiple_choice' || qtype === 'true_false') {
          container.appendChild(createExistingOptionRow({ option_text: '', is_correct: 0 }));
          container.appendChild(createExistingOptionRow({ option_text: '', is_correct: 0 }));
        } else {
          // short answer — no options by default
        }
      }

      // show/hide options UI based on type (single/multiple/true_false show, short_answer hide)
      function toggleOptionsUI(type) {
        const show = ['single_choice','multiple_choice','true_false'].includes(type);
        document.getElementById('eq_add_option_btn').style.display = show ? 'inline-block' : 'none';
      }
      toggleOptionsUI(qtype);

      // change handler for type select to toggle UI (and ensure at least one option row)
      const typeSelect = document.getElementById('eq_question_type');
      typeSelect.onchange = function () {
        toggleOptionsUI(this.value);
        const cont = document.getElementById('eq_options_container');
        if (['single_choice','multiple_choice','true_false'].includes(this.value) && cont.children.length === 0) {
          cont.appendChild(createExistingOptionRow({ option_text:'', is_correct:0 }));
          cont.appendChild(createExistingOptionRow({ option_text:'', is_correct:0 }));
        }
      };

    });
  });

  // Client side validation before submit: require at least one option for choice types
  const form = document.getElementById('editQuestionForm');
  form.addEventListener('submit', function (ev) {
    const qtext = document.getElementById('eq_question_text').value.trim();
    const qtype = document.getElementById('eq_question_type').value;
    const marks = document.getElementById('eq_marks').value;
    let valid = true;

    if (!qtext) valid = false;
    if (!marks || isNaN(parseInt(marks,10))) valid = false;

    if (['single_choice','multiple_choice','true_false'].includes(qtype)) {
      const optRows = document.querySelectorAll('#eq_options_container .eq-option-row');
      if (optRows.length === 0) {
        valid = false;
        alert('Please add at least one option for this question type.');
      } else {
        // ensure no empty option text
        optRows.forEach(r => {
          const txt = r.querySelector('input[type="text"]');
          if (txt && txt.value.trim() === '') valid = false;
        });
        if (!valid) {
          alert('Option text cannot be empty. Please fill or remove empty options.');
        }
      }
    }

    if (!valid) {
      ev.preventDefault();
      ev.stopPropagation();
      form.classList.add('was-validated');
      return false;
    }
    return true;
  });

})();
</script>

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

    <?php if (!empty($errors)): ?>
    var modal = new bootstrap.Modal(addModalEl);
    modal.show();
    <?php endif; ?>
}

function confirmStatusChange(form) {
    var sel = form.querySelector('select[name="status"]');
    var chosen = sel.options[sel.selectedIndex].text;
    return confirm('Change exam status to "' + chosen + '"?');
}
</script>

</body>
</html>
