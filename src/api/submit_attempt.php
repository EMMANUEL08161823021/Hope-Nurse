<?php
// src/api/submit_attempt.php
require_once '../config/db.php';
require_once '../middleware/auth.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$attempt_id = (int)($data['attempt_id'] ?? 0);
$auto = !empty($data['auto']);

if (!$attempt_id) { http_response_code(400); echo json_encode(['error'=>'Missing attempt']); exit; }

$student_id = $_SESSION['user']['id'];

// Lock attempt row to prevent race
$pdo->beginTransaction();
$attSt = $pdo->prepare("SELECT * FROM attempts WHERE id = ? FOR UPDATE");
$attSt->execute([$attempt_id]);
$attempt = $attSt->fetch();
if (!$attempt || $attempt['student_id'] != $student_id) { $pdo->rollBack(); http_response_code(403); echo json_encode(['error'=>'Forbidden']); exit; }
if ($attempt['status'] !== 'in_progress') { $pdo->rollBack(); echo json_encode(['error'=>'Already submitted']); exit; }

// load questions
$qst = $pdo->prepare("SELECT * FROM questions WHERE exam_id = ?");
$qst->execute([$attempt['exam_id']]);
$questions = $qst->fetchAll();

// load options map
$optsStmt = $pdo->prepare("SELECT * FROM options WHERE question_id = ?");
$ansSelect = $pdo->prepare("SELECT answer_text FROM answers WHERE attempt_id = ? AND question_id = ? LIMIT 1");

// prepare update for answers
$updateAns = $pdo->prepare("UPDATE answers SET is_correct = :is_correct, awarded_marks = :awarded WHERE attempt_id = :att AND question_id = :qid");

// scoring
$totalScore = 0;

foreach ($questions as $q) {
    $qid = $q['id'];
    $qtype = $q['question_type'];
    $marks = (float)$q['marks'];

    // fetch saved answer
    $ansSelect->execute([$attempt_id, $qid]);
    $aRow = $ansSelect->fetch();
    $given = $aRow ? $aRow['answer_text'] : null;

    // load correct options when applicable
    $optsStmt->execute([$qid]);
    $optsAll = $optsStmt->fetchAll();

    $awarded = 0;
    $isCorrect = 0;

    if (in_array($qtype, ['single_choice','true_false'])) {
        // given should be option id
        $correctOpt = null;
        foreach ($optsAll as $o) if ($o['is_correct']) { $correctOpt = $o['id']; break; }
        if ($given !== null && (string)$correctOpt === (string)$given) { $awarded = $marks; $isCorrect = 1; }
    } elseif ($qtype === 'multiple_choice') {
        // compare sets
        $correctIds = [];
        foreach ($optsAll as $o) if ($o['is_correct']) $correctIds[] = (string)$o['id'];
        $givenIds = [];
        if ($given) {
            $decoded = json_decode($given, true);
            if (is_array($decoded)) foreach ($decoded as $v) $givenIds[] = (string)$v;
        }
        sort($correctIds); sort($givenIds);
        if ($correctIds === $givenIds && count($correctIds) > 0) { $awarded = $marks; $isCorrect = 1; }
    } elseif (in_array($qtype, ['short_answer','fill_blank'])) {
        // find canonical correct answer (first option with is_correct)
        $correctText = null;
        foreach ($optsAll as $o) if ($o['is_correct']) { $correctText = trim($o['option_text']); break; }
        if ($correctText !== null && $given !== null) {
            if (mb_strtolower(trim($given)) === mb_strtolower($correctText)) { $awarded = $marks; $isCorrect = 1; }
        } else {
            // leave for manual grading (awarded=0)
            $awarded = 0; $isCorrect = 0;
        }
    }

    // update answer row (create if missing)
    $ins = $pdo->prepare("INSERT INTO answers (attempt_id, question_id, answer_text, is_correct, awarded_marks) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE answer_text=VALUES(answer_text), is_correct=VALUES(is_correct), awarded_marks=VALUES(awarded_marks), saved_at=NOW()");
    $ins->execute([$attempt_id, $qid, $given, $isCorrect, $awarded]);

    $totalScore += $awarded;
}

// finalize attempt
$ended_at = (new DateTime())->format('Y-m-d H:i:s');
$status = $auto ? 'auto_submitted' : 'submitted';
$upd = $pdo->prepare("UPDATE attempts SET status = ?, submitted_at = ?, score = ? WHERE id = ?");
$upd->execute([$status, $ended_at, $totalScore, $attempt_id]);

$pdo->commit();
echo json_encode(['success'=>true, 'score'=>$totalScore]);
exit;
