<?php
// src/student/take_exam.php
require_once '../middleware/auth.php';
if ($_SESSION['user']['role'] !== 'student') die('Forbidden');
require_once '../config/db.php';

$attempt_id = intval($_GET['attempt_id'] ?? 0);
if (!$attempt_id) die('Missing attempt');

$stmt = $pdo->prepare("SELECT a.*, e.title, e.duration AS exam_duration FROM attempts a JOIN exams e ON a.exam_id=e.id WHERE a.id=? AND a.student_id=? LIMIT 1");
$stmt->execute([$attempt_id, $_SESSION['user']['id']]);
$attempt = $stmt->fetch();
if (!$attempt) die('Attempt not found or not yours');

if ($attempt['status'] !== 'in_progress') {
    die('This attempt is not in progress.');
}

// compute end time server-side
$started = new DateTime($attempt['started_at']);
$endTime = clone $started;
$endTime->modify('+'.((int)$attempt['duration_minutes']).' minutes');

// load questions with options
$qstmt = $pdo->prepare("SELECT * FROM questions WHERE exam_id = ? ORDER BY id ASC");
$qstmt->execute([$attempt['exam_id']]);
$questions = $qstmt->fetchAll();

// load existing answers for this attempt
$ansStmt = $pdo->prepare("SELECT question_id, answer_text FROM answers WHERE attempt_id = ?");
$ansStmt->execute([$attempt_id]);
$saved = [];
foreach ($ansStmt->fetchAll() as $r) $saved[$r['question_id']] = $r['answer_text'];

?>
<?php require '../constants/header.php'?>
  <title>Take Exam — <?= htmlspecialchars($attempt['title']) ?></title>
  <style>.question { margin-bottom: 1.5rem; }</style>
</head>
<body class="container py-4">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4><?= htmlspecialchars($attempt['title']) ?></h4>
    <div>
      Time left: <span id="timer">--:--</span>
    </div>
  </div>

  <form id="examForm">

    <input type="hidden" id="attempt_id" name="attempt_id" value="<?= $attempt_id ?>">
    <input type="hidden" id="end_time_iso" value="<?= htmlspecialchars($endTime->format(DateTime::ATOM)) ?>">

    <?php foreach ($questions as $q): ?>
      <div class="question border p-3" data-qid="<?= $q['id'] ?>">
        <div><strong>Q<?= $q['id'] ?>.</strong> <?= nl2br(htmlspecialchars($q['question_text'])) ?></div>
        <div class="mt-2">
          <?php
            $qid = $q['id'];
            $savedAnswer = $saved[$qid] ?? null;

            if (in_array($q['question_type'], ['single_choice','multiple_choice','true_false'])):
              // load options

              $opt = $pdo->prepare("SELECT * FROM options WHERE question_id = ? ORDER BY id ASC");
              $opt->execute([$qid]);
              $opts = $opt->fetchAll();
              if ($q['question_type'] === 'single_choice' || $q['question_type'] === 'true_false'):
                  foreach ($opts as $o):
                      $checked = ($savedAnswer !== null && trim($savedAnswer) === (string)$o['id']) ? 'checked' : '';
          ?>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="q_<?= $qid ?>" value="<?= $o['id'] ?>" id="opt<?= $o['id'] ?>" <?= $checked ?>>
              <label class="form-check-label" for="opt<?= $o['id'] ?>"><?= htmlspecialchars($o['option_text']) ?></label>
            </div>
          <?php
                  endforeach;
              else: // multiple_choice
                  $selected = [];
                  if ($savedAnswer) {
                      $selected = json_decode($savedAnswer, true) ?: [];
                  }
                  foreach ($opts as $o):
                      $checked = in_array($o['id'], $selected) ? 'checked' : '';
          ?>
                      <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="q_<?= $qid ?>[]" value="<?= $o['id'] ?>" id="opt<?= $o['id'] ?>" <?= $checked ?>>
                        <label class="form-check-label" for="opt<?= $o['id'] ?>"><?= htmlspecialchars($o['option_text']) ?></label>
                      </div>
          <?php
                  endforeach;
              endif;

            else: // short_answer or fill_blank
                $textVal = $savedAnswer ? htmlspecialchars($savedAnswer) : '';
          ?>
              <textarea name="q_<?= $qid ?>" class="form-control" rows="2"><?= $textVal ?></textarea>
          <?php endif; ?>

        </div>
      </div>
    <?php endforeach; ?>

    <div class="mb-4">
      <button id="submitBtn" type="button" class="btn btn-success">Submit Exam</button>
    </div>
  </form>

<script>
/* Timer */
const endTime = new Date(document.getElementById('end_time_iso').value);
const timerEl = document.getElementById('timer');
const attemptId = document.getElementById('attempt_id').value;

function updateTimer() {
  const now = new Date();
  let diff = Math.max(0, Math.floor((endTime - now) / 1000));
  const mins = Math.floor(diff / 60);
  const secs = diff % 60;
  timerEl.textContent = String(mins).padStart(2,'0') + ':' + String(secs).padStart(2,'0');
  if (diff <= 0) {
    autoSubmit();
    clearInterval(timerInterval);
  }
}
const timerInterval = setInterval(updateTimer, 1000);
updateTimer();

/* Collect answers */
function collectAnswers() {
  const data = { attempt_id: attemptId, answers: [] };
  document.querySelectorAll('.question').forEach(q => {
    const qid = q.dataset.qid;
    // find inputs with name starting with q_{qid}
    const radio = q.querySelector('input[type="radio"][name="q_'+qid+'"]');
    if (radio) {
      const selected = q.querySelector('input[type="radio"][name="q_'+qid+'"]:checked');
      data.answers.push({question_id: qid, answer: selected ? selected.value : null});
      return;
    }
    const checkboxes = q.querySelectorAll('input[type="checkbox"][name="q_'+qid+'[]"]');
    if (checkboxes.length) {
      const vals = [];
      checkboxes.forEach(cb => { if (cb.checked) vals.push(cb.value); });
      data.answers.push({question_id: qid, answer: JSON.stringify(vals)});
      return;
    }
    const txt = q.querySelector('textarea[name="q_'+qid+'"]');
    if (txt) {
      data.answers.push({question_id: qid, answer: txt.value.trim()});
    }
  });
  return data;
}

/* Autosave every 10s */
setInterval(()=> {
  const payload = collectAnswers();
  if (!payload.answers.length) return;
  navigator.sendBeacon('/hope-nurse/src/api/save_answers.php', JSON.stringify(payload));
}, 10000);

/* Manual save + submit */
document.getElementById('submitBtn').addEventListener('click', ()=> {
  console.log("click");
  
  saveThenSubmit(false);
});

/* Save via fetch then submit */
function saveThenSubmit(isAuto) {
  const payload = collectAnswers();
  fetch('/hope-nurse/src/api/save_answers.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify(payload)
  }).then(()=> {
    // then call submit
    fetch('/hope-nurse/src/api/submit_attempt.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({attempt_id: attemptId, auto: isAuto ? 1 : 0})
    }).then(r=>r.json()).then(res=> {
      if (res.success) {
        alert('Exam submitted. Score: ' + res.score);
        window.location.href = '/hope-nurse/src/student/result.php?attempt_id=' + attemptId;
      } else {
        alert('Submission failed: ' + (res.error || 'Unknown'));
      }
    });
  });
}

/* Auto submit on timeout */
function autoSubmit() {
  saveThenSubmit(true);
}

/* Save on page unload using sendBeacon */
window.addEventListener('beforeunload', function(e) {
  const payload = collectAnswers();
  if (payload.answers.length) {
    navigator.sendBeacon('/hope-nurse/src/api/save_answers.php', JSON.stringify(payload));
  }
});
</script>
</body>
</html>
