<?php
// src/middleware/auth.php
session_start();
require_once __DIR__ . '/../config/db.php';

/**
 * Ensure a user is logged in and has the required role.
 * If the user's status is 'blocked', destroy the session and redirect to login.
 *
 * @param string $requiredRole e.g. 'admin' or 'student'
 */
function requireRole(string $requiredRole) {
    global $pdo;

    // Not logged in
    if (empty($_SESSION['user']['id'])) {
        header('Location: ../auth/login.php');
        exit;
    }

    $userId = (int)$_SESSION['user']['id'];

    // Pull latest role/status from DB
    $stmt = $pdo->prepare("SELECT role, status FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $dbUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$dbUser) {
        // Account removed — destroy session
        session_unset();
        session_destroy();
        header('Location: ../auth/login.php');
        exit;
    }

    // If blocked, invalidate session and redirect to login with message
    if (isset($dbUser['status']) && $dbUser['status'] === 'blocked') {
        session_unset();
        session_destroy();
        // start new session to carry the message
        session_start();
        $_SESSION['login_error'] = 'Your account has been blocked. Contact an administrator.';
        header('Location: ../auth/login.php');
        exit;
    }

    // Enforce role
    if ($dbUser['role'] !== $requiredRole) {
        http_response_code(403);
        echo 'Access denied.';
        exit;
    }

    // Keep session in sync
    $_SESSION['user']['role'] = $dbUser['role'];
    $_SESSION['user']['status'] = $dbUser['status'] ?? 'active';
}
