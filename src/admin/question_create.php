<?php
require_once __DIR__ . '/../middleware/auth.php';
requireRole('admin');
require_once __DIR__ . '/../middleware/csrf.php';
require_once __DIR__ . '/../config/db.php';

// Need an exam_id param to add question to an exam
$exam_id = intval($_GET['exam_id'] ?? 0);
if (!$exam_id) {
    die('Missing exam id');
}

// optional: fetch exam to show title
$stmt = $pdo->prepare("SELECT id, title FROM exams WHERE id = ?");
$stmt->execute([$exam_id]);
$exam = $stmt->fetch();
if (!$exam) die('Exam not found');
?>
<?php require '../constants/header.php'?>

  <title>Add Question — <?= htmlspecialchars($exam['title']); ?></title>
</head>
<body class="container py-4">
  <h3>Add Question to: <?= htmlspecialchars($exam['title']); ?></h3>

  <form id="questionForm" method="post" action="/admin/questions_store.php">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()); ?>">
    <input type="hidden" name="exam_id" value="<?= (int)$exam_id; ?>">

    <div class="mb-3">
      <label class="form-label">Question text</label>
      <textarea name="question_text" class="form-control" rows="3" required></textarea>
    </div>

    <div class="mb-3">
      <label class="form-label">Question type</label>
      <select name="question_type" id="question_type" class="form-select" required>
        <option value="single_choice">Single choice (one correct)</option>
        <option value="multiple_choice">Multiple choice (select all that apply)</option>
        <option value="true_false">True / False</option>
        <option value="short_answer">Short answer (text)</option>
        <option value="fill_blank">Fill in the blank</option>
      </select>
    </div>

    <div id="optionsSection" class="mb-3">
      <label class="form-label">Options</label>

      <div id="optionsList">
        <!-- JS will populate option rows for choice types -->
      </div>

      <button type="button" id="addOptionBtn" class="btn btn-sm btn-outline-secondary mt-2">Add option</button>
      <div class="form-text">For single_choice pick exactly one correct option; for multiple_choice mark multiple correct options.</div>
    </div>

    <div id="correctAnswerSection" class="mb-3" style="display:none;">
      <label class="form-label">Correct answer (for short_answer / fill_blank)</label>
      <input name="correct_answer" class="form-control">
      <div class="form-text">Optional: used for auto-grading if you implement string matching or exact match.</div>
    </div>

    <div class="mb-3">
      <label class="form-label">Marks</label>
      <input name="marks" type="number" min="0" value="1" class="form-control" required>
    </div>

    <div class="mt-3">
      <button class="btn btn-primary">Save Question</button>
      <a href="/admin/exams.php" class="btn btn-secondary">Back</a>
    </div>
  </form>

<script>
document.addEventListener('DOMContentLoaded', function(){
  const qType = document.getElementById('question_type');
  const optionsSection = document.getElementById('optionsSection');
  const correctSection = document.getElementById('correctAnswerSection');
  const optionsList = document.getElementById('optionsList');
  const addOptionBtn = document.getElementById('addOptionBtn');

  function clearOptions(){ optionsList.innerHTML = ''; }

  function addOptionRow(text = '', isCorrect = false){
    const idx = Date.now() + Math.random();
    const wrapper = document.createElement('div');
    wrapper.className = 'input-group mb-2 option-row';
    wrapper.innerHTML = `
      <span class="input-group-text">
        <input type="checkbox" name="option_is_correct[]" value="${idx}" ${isCorrect ? 'checked' : ''} aria-label="Correct">
      </span>
      <input type="text" name="option_text[]" class="form-control" placeholder="Option text" value="${text}" required>
      <button type="button" class="btn btn-outline-danger remove-option">Remove</button>
      <input type="hidden" name="option_key[]" value="${idx}">
    `;
    optionsList.appendChild(wrapper);

    wrapper.querySelector('.remove-option').addEventListener('click', ()=> wrapper.remove());
  }

  function setTypeUI(){
    const t = qType.value;
    if (t === 'single_choice' || t === 'multiple_choice' || t === 'true_false') {
      optionsSection.style.display = 'block';
      correctSection.style.display = 'none';
      if (t === 'true_false') {
        // prefill True / False if empty
        clearOptions();
        addOptionRow('True', true);
        addOptionRow('False', false);
        addOptionBtn.style.display = 'none';
      } else {
        if (!optionsList.querySelector('.option-row')) {
          addOptionRow('', false);
          addOptionRow('', false);
        }
        addOptionBtn.style.display = 'inline-block';
      }
    } else {
      optionsSection.style.display = 'none';
      correctSection.style.display = 'block';
      optionsList.innerHTML = '';
      addOptionBtn.style.display = 'none';
    }
  }

  qType.addEventListener('change', setTypeUI);
  addOptionBtn.addEventListener('click', ()=> addOptionRow());

  setTypeUI();
});
</script>
</body>
</html>
