<?php
require_once '../middleware/auth.php';
requireRole('admin');
require_once '../db.php';

$id = intval($_GET['id'] ?? 0);
if (!$id) die('Invalid request');

// Delete student
$stmt = $pdo->prepare("DELETE FROM users WHERE id=? AND role='student'");
$stmt->execute([$id]);

$_SESSION['flash'] = 'Student deleted';
header('Location: students.php');
exit;
