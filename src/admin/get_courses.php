<?php
require_once __DIR__ . '/../middleware/auth.php';
requireRole('admin');

require_once '../config/db.php';

header('Content-Type: application/json');

if (empty($_GET['program_id'])) {
    echo json_encode([
        'success' => false,
        'data' => []
    ]);
    exit;
}

$programId = (int) $_GET['program_id'];

try {
    $stmt = $pdo->prepare("
        SELECT id, title
        FROM courses
        WHERE program_id = :pid
          AND is_active = 1
        ORDER BY title ASC
    ");
    $stmt->execute([':pid' => $programId]);

    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $courses
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'data' => []
    ]);
}
