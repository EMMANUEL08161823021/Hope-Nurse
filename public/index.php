<?php
session_start();

if (
    !isset($_SESSION['user']) ||
    !isset($_SESSION['user']['id']) ||
    !isset($_SESSION['user']['role'])
) {
    header("Location: ../src/auth/login.php");
    exit;
}

if ($_SESSION['user']['role'] === 'admin') {
    header("Location: ../src/admin/dashboard.php");
} else {
    header("Location: ../src/student/dashboard.php");
}
exit;
