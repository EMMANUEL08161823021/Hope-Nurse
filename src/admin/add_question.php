<?php
require_once '../middleware/auth.php';
requireRole('admin');
require_once '../config/db.php';

$exam_id = (int)($_GET['exam_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $stmt = $pdo->prepare("
        INSERT INTO questions (exam_id, question_text, question_type, marks)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([
        $_POST['exam_id'],
        $_POST['question_text'],
        $_POST['question_type'],
        $_POST['marks']
    ]);

    $question_id = $pdo->lastInsertId();

    // OPTION-BASED QUESTIONS
    if (!empty($_POST['options'])) {
        foreach ($_POST['options'] as $i => $text) {
            if (trim($text) === '') continue;

            $is_correct = in_array($i, $_POST['correct'] ?? []) ? 1 : 0;

            $opt = $pdo->prepare("
                INSERT INTO question_options (question_id, option_text, is_correct)
                VALUES (?, ?, ?)
            ");
            $opt->execute([$question_id, $text, $is_correct]);
        }
    }

    // TEXT ANSWER QUESTIONS
    if (!empty($_POST['correct_answer'])) {
        $opt = $pdo->prepare("
            INSERT INTO question_options (question_id, option_text, is_correct)
            VALUES (?, ?, 1)
        ");
        $opt->execute([$question_id, trim($_POST['correct_answer'])]);
    }

    header("Location: questions.php?exam_id=".$_POST['exam_id']);
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add Question</title>
    <link rel="stylesheet" href="../assets/bootstrap.min.css">
</head>
<body>
<div class="container mt-4">

<h3>Add Question</h3>

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
    <?php for ($i=0;$i<4;$i++): ?>
        <div class="input-group mb-2">
            <input type="text" name="options[]" class="form-control">
            <span class="input-group-text">
                <input type="checkbox" name="correct[]" value="<?= $i ?>">
            </span>
        </div>
    <?php endfor; ?>
</div>

<div id="answerBox" style="display:none;">
    <label>Correct Answer</label>
    <input type="text" name="correct_answer" class="form-control">
</div>

<button class="btn btn-success mt-3">Save Question</button>

</form>
</div>

<script>
function toggleFields() {
    let type = document.getElementById('type').value;
    document.getElementById('optionsBox').style.display =
        ['single_choice','multiple_choice','true_false'].includes(type) ? 'block' : 'none';
    document.getElementById('answerBox').style.display =
        ['short_answer','fill_blank'].includes(type) ? 'block' : 'none';
}
</script>
</body>
</html>
