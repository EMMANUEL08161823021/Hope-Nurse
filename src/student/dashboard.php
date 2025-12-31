<?php
require_once '../middleware/auth.php';
requireRole('student');
?>

<h2>Student Dashboard</h2>
<p>Welcome, <?= htmlspecialchars($_SESSION['user']['name']) ?></p>

<ul>
    <li><a href="#">Available Exams</a></li>
    <li><a href="#">My Results</a></li>
</ul>

<a href="../auth/logout.php">Logout</a>
