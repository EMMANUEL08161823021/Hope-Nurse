<?php
require_once '../middleware/auth.php';
requireRole('admin');

$student_id = intval($_GET['id'] ?? 0);
?>

<h3>Student Attempts</h3>
<p>This page will show exam attempts, scores, and timestamps.</p>
<p>Student ID: <?= $student_id ?></p>
