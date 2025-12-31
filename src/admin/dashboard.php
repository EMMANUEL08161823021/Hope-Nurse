<?php
require_once '../middleware/auth.php';
requireRole('admin');
?>

<h2>Admin Dashboard</h2>
<p>Welcome, <?= htmlspecialchars($_SESSION['user']['name']) ?></p>

<ul>
    <li><a href="#">Create Exam</a></li>
    <li><a href="#">Manage Questions</a></li>
    <li><a href="#">View Students</a></li>
</ul>

<a href="../auth/logout.php">Logout</a>
