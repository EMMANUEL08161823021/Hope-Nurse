<?php
require_once '../middleware/auth.php';
requireRole('admin');
require_once '../db.php';

$id = intval($_GET['id'] ?? 0);
if (!$id) die('Invalid request');

// Toggle status
$stmt = $pdo->prepare("
    UPDATE users 
    SET status = IF(status='active','blocked','active')
    WHERE id = ? AND role='student'
");
$stmt->execute([$id]);

$_SESSION['flash'] = 'Student status updated';
header('Location: students.php');
exit;
