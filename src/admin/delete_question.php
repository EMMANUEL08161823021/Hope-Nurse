<?php
require_once '../middleware/auth.php';
requireRole('admin');
require_once '../config/db.php';

$id = (int)$_GET['id'];
$exam_id = (int)$_GET['exam_id'];

$pdo->prepare("DELETE FROM questions WHERE id=?")->execute([$id]);

header("Location: questions.php?exam_id=".$exam_id);
exit;
