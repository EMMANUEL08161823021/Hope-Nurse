<?php
require_once __DIR__ . '/../middleware/auth.php';
requireRole('student');

// === Adjust this include to your actual database bootstrap file ===
// It must provide a PDO instance in $pdo
require_once __DIR__ . '/../config/db.php';
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

    <?php
    // Greet the user: prefer full_name, fallback to email
    $greetName = 'Student';
    if (!empty($_SESSION['user']['full_name'])) {
        $greetName = $_SESSION['user']['full_name'];
    } elseif (!empty($_SESSION['user']['email'])) {
        $greetName = $_SESSION['user']['email'];
    }

    // Determine user's program (prefer session, fallback to DB)
    $programName = null;

    if (!empty($_SESSION['user']['program'])) {
        $programName = $_SESSION['user']['program'];
    } elseif (!empty($_SESSION['user']['id'])) {
        // fallback: fetch from users table
        try {
            $stmt = $pdo->prepare("SELECT program FROM users WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $_SESSION['user']['id']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && !empty($row['program'])) {
                $programName = $row['program'];
            }
        } catch (Exception $e) {
            // log server-side; show a friendly message below
            error_log("Failed to read user program: " . $e->getMessage());
        }
    }

    $courses = [];
    $coursesError = null;

    if ($programName) {
        try {
            // Get program id from programs table
            $stmt = $pdo->prepare("SELECT id FROM programs WHERE name = :name LIMIT 1");
            $stmt->execute([':name' => $programName]);
            $prog = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($prog && !empty($prog['id'])) {
                $programId = (int)$prog['id'];

                // Fetch active courses for this program
                $stmt = $pdo->prepare("
                    SELECT id, title, description
                    FROM courses
                    WHERE program_id = :pid
                      AND is_active = 1
                    ORDER BY id ASC
                ");
                $stmt->execute([':pid' => $programId]);
                $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $coursesError = "Program \"".htmlspecialchars($programName)."\" not found in the system.";
            }
        } catch (Exception $e) {
            error_log("Failed to load courses for program {$programName}: " . $e->getMessage());
            $coursesError = "Unable to load courses at this time. Please try again later.";
        }
    } else {
        $coursesError = "No program assigned. Please contact support if this is an error.";
    }

    // Compute completed courses count for this user within this program's courses.
    $completedCount = 0;
    $totalCourses = count($courses);

    if ($totalCourses > 0 && !empty($_SESSION['user']['id'])) {
        $userId = (int)$_SESSION['user']['id'];
        $courseIds = array_map(function($c){ return (int)$c['id']; }, $courses);
        $placeholders = implode(',', array_fill(0, count($courseIds), '?'));

        // Try a few conventional progress/result tables in order of preference.
        // Each try is wrapped in try/catch so we fail gracefully if the table doesn't exist.
        try {
            // 1) user_courses (progress/completed)
            $sql = "SELECT COUNT(DISTINCT course_id) AS cnt
                    FROM user_courses
                    WHERE user_id = ?
                      AND course_id IN ($placeholders)
                      AND (progress >= 100 OR completed_at IS NOT NULL)";
            $params = array_merge([$userId], $courseIds);
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && isset($row['cnt'])) {
                $completedCount = (int)$row['cnt'];
            }
        } catch (Exception $e) {
            // table probably doesn't exist — ignore and try next
        }

        if ($completedCount === 0) {
            try {
                // 2) course_progress (alternative schema)
                $sql = "SELECT COUNT(DISTINCT course_id) AS cnt
                        FROM course_progress
                        WHERE user_id = ?
                          AND course_id IN ($placeholders)
                          AND (progress >= 100 OR completed_at IS NOT NULL)";
                $params = array_merge([$userId], $courseIds);
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row && isset($row['cnt'])) {
                    $completedCount = (int)$row['cnt'];
                }
            } catch (Exception $e) {
                // ignore and try next
            }
        }

        if ($completedCount === 0) {
            try {
                // 3) exam_results / exam_attempts fallback - counts distinct course_id where user has any attempt/result
                $sql = "SELECT COUNT(DISTINCT course_id) AS cnt
                        FROM exam_results
                        WHERE user_id = ?
                          AND course_id IN ($placeholders)
                          AND (status = 'passed' OR score IS NOT NULL)";
                $params = array_merge([$userId], $courseIds);
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row && isset($row['cnt'])) {
                    $completedCount = (int)$row['cnt'];
                }
            } catch (Exception $e) {
                // all attempts failed — leave completedCount as 0
            }
        }
    }
    ?>

    <div class="row mt-4">
        <div class="col-12 mb-3">
            <h3>Hello <?= htmlspecialchars($greetName) ?></h3>
        </div>

        <?php require 'exam.php' ?>

        <!-- COURSES (display program courses in left/right layout) -->
        <div class="col-md-6 mb-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h5 class="card-title mb-0">Courses for
                            <small class="text-muted"><?= $programName ? htmlspecialchars($programName) : '—' ?></small>
                        </h5>
                        <div class="small text-muted">
                            <?= (int)$completedCount ?> / <?= (int)$totalCourses ?> completed
                        </div>
                    </div>

                    <?php if ($coursesError): ?>
                        <div class="alert alert-warning small">
                            <?= htmlspecialchars($coursesError) ?>
                        </div>
                    <?php elseif (empty($courses)): ?>
                        <p class="text-muted small">No courses are available for your program yet.</p>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($courses as $course): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-start">
                                    <div class="ms-2 me-auto">
                                        <div class="fw-bold"><?= htmlspecialchars($course['title']) ?></div>
                                        <div class="small text-muted">
                                            <?= htmlspecialchars(substr($course['description'] ?? '', 0, 160)) ?>
                                            <?= (isset($course['description']) && strlen($course['description']) > 160) ? '…' : '' ?>
                                        </div>
                                    </div>

                                    <!-- Modal trigger -->
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-primary"
                                        data-bs-toggle="modal"
                                        data-bs-target="#courseModal<?= (int)$course['id'] ?>"
                                    >
                                        View
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Modals for each course -->
                        <?php foreach ($courses as $course): ?>
                            <div class="modal fade" id="courseModal<?= (int)$course['id'] ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title"><?= htmlspecialchars($course['title']) ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <p class="small text-muted"><?= nl2br(htmlspecialchars($course['description'] ?? 'No description available.')) ?></p>

                                            <hr>

                                            <p class="small text-muted mb-2">Course actions</p>
                                            <div class="d-flex gap-2">
                                                <a href="course.php?id=<?= (int)$course['id'] ?>" class="btn btn-primary btn-sm">
                                                    Open Course Page
                                                </a>
                                                <a href="course_lessons.php?course_id=<?= (int)$course['id'] ?>" class="btn btn-outline-secondary btn-sm">
                                                    See Lessons
                                                </a>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                </div>
            </div>
        </div>

        <!-- RESULTS -->
        <div class="col-md-6 mb-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">My Results</h5>
                    <p class="card-text">
                        Review completed exams and scores.
                    </p>
                    <a href="results.php" class="btn btn-primary">
                        My Results
                    </a>
                </div>
            </div>
        </div>

    </div>

    <hr>

    <a href="../auth/logout.php" class="btn btn-outline-danger">
        Logout
    </a>

    <!-- Bootstrap JS for modals (ensure this file exists at this path) -->
    <script src="../../public/assets/dist/js/bootstrap.bundle.min.js"></script></body>
</html>
