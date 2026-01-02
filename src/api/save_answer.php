<?php
// src/api/save_answers.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/auth.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || empty($data['attempt_id'])) { http_response_code(400); echo json_encode(['error'=>'Invalid']); exit; }
$attempt_id = (int)$data['attempt_id'];
$student_id = $_SESSION['user']['id'];

// verify attempt ownership and in_progress
$st = $pdo->prepare("SELECT * FROM attempts WHERE id = ? AND student_id = ? LIMIT 1");
$st->execute([$attempt_id, $student_id]);
$att = $st->fetch();
if (!$att || $att['status'] !== 'in_progress') { http_response_code(403); echo json_encode(['error'=>'Forbidden']); exit; }

$answers = $data['answers'] ?? [];
if (!is_array($answers)) $answers = [];

$upsert = $pdo->prepare("
  INSERT INTO answers (attempt_id, question_id, answer_text, saved_at)
  VALUES (:attempt, :qid, :ans, NOW())
  ON DUPLICATE KEY UPDATE answer_text = VALUES(answer_text), saved_at = NOW()
");

foreach ($answers as $a) {
    $qid = (int)($a['question_id'] ?? 0);
    $ans = $a['answer'] === null ? null : (string)$a['answer'];
    if (!$qid) continue;
    $upsert->execute([':attempt'=>$attempt_id, ':qid'=>$qid, ':ans'=>$ans]);
}

echo json_encode(['saved'=>true]);
exit;
