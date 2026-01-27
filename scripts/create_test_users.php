<?php
require_once __DIR__ . '/../src/config/db.php';

function createUser($pdo, $full_name, $email, $password, $role='student', $program=null) {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, role, status, program) VALUES (?, ?, ?, ?, 'active', ?)");
    return $stmt->execute([$full_name, $email, $hash, $role, $program]);
}

try {
    createUser($pdo, 'Admin User', 'admin@example.com', 'AdminPass123!', 'admin', null);
    createUser($pdo, 'Test Student', 'student@example.com', 'StudentPass123!', 'student', 'Hope Prep');
    echo "Created test users: admin@example.com / AdminPass123! and student@example.com / StudentPass123!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
