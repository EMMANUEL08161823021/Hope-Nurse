<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user'])) {
    header("Location: ../auth/login.php");
    exit;
}

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

function requireRole($role)
{
    if ($_SESSION['user']['role'] !== $role) {
        die('Unauthorized access');
    }
}
