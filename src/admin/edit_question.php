<?php
require_once '../middleware/auth.php';
requireRole('admin');
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: questions.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| 1. Validate required fields
|--------------------------------------------------------------------------
*/
$questionId   = isset($_POST['question_id']) ? (int)$_POST['question_id'] : 0;
$questionText = trim($_POST['question_text'] ?? '');
$questionType = $_POST['question_type'] ?? '';
$marks        = isset($_POST['marks']) ? (int)$_POST['marks'] : 0;

$allowedTypes = ['single_choice','multiple_choice','true_false','short_answer'];

if (
    $questionId <= 0 ||
    $questionText === '' ||
    !in_array($questionType, $allowedTypes, true) ||
    $marks < 0
) {
    $_SESSION['flash'] = 'Invalid question data.';
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'questions.php'));
    exit;
}

/*
|--------------------------------------------------------------------------
| 2. Start transaction
|--------------------------------------------------------------------------
*/
$pdo->beginTransaction();

try {

    /*
    |--------------------------------------------------------------------------
    | 3. Update question
    |--------------------------------------------------------------------------
    */
    $updateQuestion = $pdo->prepare("\n        UPDATE questions\n        SET\n            question_text = :text,\n            question_type = :type,\n            marks = :marks\n        WHERE id = :id\n    ");

    $updateQuestion->execute([
        ':text'  => $questionText,
        ':type'  => $questionType,
        ':marks' => $marks,
        ':id'    => $questionId
    ]);

    /*
    |--------------------------------------------------------------------------
    | 4. Delete removed options
    |--------------------------------------------------------------------------
    */
    if (!empty($_POST['removed_option_ids']) && is_array($_POST['removed_option_ids'])) {
        $deleteStmt = $pdo->prepare("\n            DELETE FROM options\n            WHERE id = :id AND question_id = :qid\n        ");

        foreach ($_POST['removed_option_ids'] as $optId) {
            $deleteStmt->execute([
                ':id'  => (int)$optId,
                ':qid' => $questionId
            ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 5. Update existing options
    |--------------------------------------------------------------------------
    */
    if (!empty($_POST['options_existing']) && is_array($_POST['options_existing'])) {
        $updateOpt = $pdo->prepare("\n            UPDATE options\n            SET option_text = :text, is_correct = :correct\n            WHERE id = :id AND question_id = :qid\n        ");

        foreach ($_POST['options_existing'] as $optId => $optData) {
            $text = trim($optData['text'] ?? '');
            if ($text === '') {
                continue;
            }

            $isCorrect = isset($optData['is_correct']) ? 1 : 0;

            $updateOpt->execute([
                ':text'    => $text,
                ':correct' => $isCorrect,
                ':id'      => (int)$optId,
                ':qid'     => $questionId
            ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 6. Insert new options
    |--------------------------------------------------------------------------
    */
    if (!empty($_POST['options_new']) && is_array($_POST['options_new'])) {
        $insertOpt = $pdo->prepare("\n            INSERT INTO options (question_id, option_text, is_correct)\n            VALUES (:qid, :text, :correct)\n        ");

        foreach ($_POST['options_new'] as $optData) {
            $text = trim($optData['text'] ?? '');
            if ($text === '') {
                continue;
            }

            $isCorrect = isset($optData['is_correct']) ? 1 : 0;

            $insertOpt->execute([
                ':qid'     => $questionId,
                ':text'    => $text,
                ':correct' => $isCorrect
            ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 7. Enforce correctness rules
    |--------------------------------------------------------------------------
    */
    if (in_array($questionType, ['single_choice','true_false'], true)) {
        // Ensure only ONE correct option. Some PDO drivers do not allow re-using the same named
        // parameter more than once when emulation is disabled, so bind two different names.
        $fixSql = "\n            UPDATE options\n            SET is_correct = 0\n            WHERE question_id = :qid1\n            AND id NOT IN (\n                SELECT id FROM (\n                    SELECT id\n                    FROM options\n                    WHERE question_id = :qid2 AND is_correct = 1\n                    ORDER BY id ASC\n                    LIMIT 1\n                ) x\n            )\n        ";

        $stmt = $pdo->prepare($fixSql);
        $stmt->execute([':qid1' => $questionId, ':qid2' => $questionId]);
    }

    /*
    |--------------------------------------------------------------------------
    | 8. Commit
    |--------------------------------------------------------------------------
    */
    $pdo->commit();

    $_SESSION['flash'] = 'Question updated successfully.';
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'questions.php'));
    exit;

} catch (Throwable $e) {

    $pdo->rollBack();

    // TEMPORARY DEBUG (remove after fixing)
    die(
        'Edit Question Error: ' .
        htmlspecialchars($e->getMessage())
    );
}
