<?php
require_once __DIR__ . '/../middleware/auth.php';
requireRole('student');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Dashboard | Hope Nurse Exam</title>
    <link rel="stylesheet" href="../../assets/bootstrap.min.css">
</head>
<body class="container mt-4">

    <h2>Student Dashboard</h2>
    <!-- <p class="text-muted">
        Welcome, <?= htmlspecialchars($_SESSION['user']['email']) ?>
    </p> -->

    
    <div class="row mt-4">
         <?php require 'exam.php'?>     

        <!-- RESULTS -->
        <div class="col-md-6 mb-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">My Results</h5>
                    <p class="card-text">
                        Review completed exams and scores.
                    </p>
                    <a href="result.php" class="btn btn-secondary">
                        View Results
                    </a>
                </div>
            </div>
        </div>

    </div>

    <hr>

    <a href="../auth/logout.php" class="btn btn-outline-danger">
        Logout
    </a>

</body>
</html>
