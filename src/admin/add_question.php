<?php
require_once '../middleware/auth.php';
requireRole('admin');
require_once '../config/db.php';

$exam_id = (int)($_GET['exam_id'] ?? 0);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        $question_text = trim($_POST['question_text']);
        $type = $_POST['question_type'];
        $marks = (int)$_POST['marks'];

        if ($question_text === '' || $marks <= 0) {
            throw new Exception('Question text and marks are required.');
        }

        // Insert question
        $stmt = $pdo->prepare("
            INSERT INTO questions (exam_id, question_text, question_type, marks)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$exam_id, $question_text, $type, $marks]);

        $question_id = $pdo->lastInsertId();

        /* ===============================
           SINGLE / MULTIPLE CHOICE
        =============================== */
        if (in_array($type, ['single_choice', 'multiple_choice'])) {

            if (empty($_POST['options']) || empty($_POST['correct'])) {
                throw new Exception('Options and correct answer(s) required.');
            }

            if ($type === 'single_choice' && count($_POST['correct']) !== 1) {
                throw new Exception('Single choice must have exactly one correct answer.');
            }

            foreach ($_POST['options'] as $i => $text) {
                $text = trim($text);
                if ($text === '') continue;

                $is_correct = in_array($i, $_POST['correct']) ? 1 : 0;

                $opt = $pdo->prepare("
                    INSERT INTO options (question_id, option_text, is_correct)
                    VALUES (?, ?, ?)
                ");
                $opt->execute([$question_id, $text, $is_correct]);
            }
        }

        /* ===============================
           TRUE / FALSE
        =============================== */
        if ($type === 'true_false') {
            if (!isset($_POST['correct_tf'])) {
                throw new Exception('True/False answer required.');
            }

            foreach (['True', 'False'] as $value) {
                $opt = $pdo->prepare("
                    INSERT INTO options (question_id, option_text, is_correct)
                    VALUES (?, ?, ?)
                ");
                $opt->execute([
                    $question_id,
                    $value,
                    $_POST['correct_tf'] === $value ? 1 : 0
                ]);
            }
        }

        /* ===============================
           TEXT ANSWERS
        =============================== */
        if (in_array($type, ['short_answer', 'fill_blank'])) {
            if (empty($_POST['correct_answer'])) {
                throw new Exception('Correct answer is required.');
            }

            $opt = $pdo->prepare("
                INSERT INTO options (question_id, option_text, is_correct)
                VALUES (?, ?, 1)
            ");
            $opt->execute([$question_id, trim($_POST['correct_answer'])]);
        }

        $pdo->commit();
        header("Location: questions.php?exam_id=$exam_id");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $errors[] = $e->getMessage();
    }
}
?>

<?php require __DIR__ . '/../constants/header.php'; ?>
<title>Add Question</title>
</head>
<body>

<div class="container mt-4">
<h3>Add Question</h3>

<?php if ($errors): ?>
<div class="alert alert-danger">
    <?= implode('<br>', array_map('htmlspecialchars', $errors)) ?>
</div>
<?php endif; ?>

<form method="POST">

<input type="hidden" name="exam_id" value="<?= $exam_id ?>">

<div class="mb-3">
    <label>Question</label>
    <textarea name="question_text" class="form-control" required></textarea>
</div>

<div class="mb-3">
    <label>Question Type</label>
    <select name="question_type" id="type" class="form-select" required onchange="toggleFields()">
        <option value="">Select</option>
        <option value="single_choice">Multiple Choice (Single)</option>
        <option value="multiple_choice">Select All That Apply</option>
        <option value="true_false">True / False</option>
        <option value="short_answer">Short Answer</option>
        <option value="fill_blank">Fill in the Blank</option>
    </select>
</div>

<div class="mb-3">
    <label>Marks</label>
    <input type="number" name="marks" class="form-control" required>
</div>

<div id="optionsBox" style="display:none;">
<h6>Options</h6>
<?php for ($i=0; $i<4; $i++): ?>
<div class="input-group mb-2">
    <input type="text" name="options[]" class="form-control">
    <span class="input-group-text">
        <input type="checkbox" name="correct[]" value="<?= $i ?>">
    </span>
</div>
<?php endfor; ?>
</div>

<div id="trueFalseBox" style="display:none;">
<label>Correct Answer</label>
<select name="correct_tf" class="form-select">
    <option value="">Select</option>
    <option value="True">True</option>
    <option value="False">False</option>
</select>
</div>

<div id="answerBox" style="display:none;">
<label>Correct Answer</label>
<input type="text" name="correct_answer" class="form-control">
</div>

<button class="btn btn-success mt-3">Save Question</button>
<a href="questions.php?exam_id=<?= $exam_id ?>" class="btn btn-secondary mt-3 ms-2">Cancel</a>

</form>
</div>

<script>
function toggleFields() {
    let t = document.getElementById('type').value;
    optionsBox.style.display = ['single_choice','multiple_choice'].includes(t) ? 'block' : 'none';
    trueFalseBox.style.display = t === 'true_false' ? 'block' : 'none';
    answerBox.style.display = ['short_answer','fill_blank'].includes(t) ? 'block' : 'none';
}
</script>

</body>
</html>
