<?php
// admin/settings.php
session_start();
require_once __DIR__ . '/../middleware/auth.php';
requireRole('admin');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/settings_helper.php';

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
}

$values = setting_all();

// helper to get value and fallback
function v($k, $fallback='') {
    global $values;
    return htmlspecialchars($values[$k] ?? $fallback, ENT_QUOTES);
}

// optional flash
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Admin Settings</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3>System Settings</h3>
    <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">Back</a>
  </div>

  <?php if ($flash): ?>
    <div class="alert alert-info"><?= htmlspecialchars($flash, ENT_QUOTES) ?></div>
  <?php endif; ?>

  <form method="POST" action="save_settings.php" class="needs-validation" novalidate>
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES) ?>">

    <div class="card mb-3">
      <div class="card-header">Exam Rules</div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Default Exam Duration (minutes)</label>
            <input type="number" name="default_exam_duration" class="form-control" min="1" value="<?= v('default_exam_duration','60') ?>" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Min Exam Duration (minutes)</label>
            <input type="number" name="min_exam_duration" class="form-control" min="1" value="<?= v('min_exam_duration','10') ?>" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Max Exam Duration (minutes)</label>
            <input type="number" name="max_exam_duration" class="form-control" min="1" value="<?= v('max_exam_duration','180') ?>" required>
          </div>
        </div>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header">Question Rules</div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Min Questions per Exam</label>
            <input type="number" name="min_questions_per_exam" class="form-control" min="1" value="<?= v('min_questions_per_exam','1') ?>" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Max Questions per Exam</label>
            <input type="number" name="max_questions_per_exam" class="form-control" min="1" value="<?= v('max_questions_per_exam','100') ?>" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Suggested Exam Time (display only)</label>
            <input type="text" name="suggested_exam_time" class="form-control" placeholder="e.g. 30-45" value="<?= v('suggested_exam_time','30-45') ?>">
            <div class="form-text">Shown to admins as guidance: ranges (not enforced).</div>
          </div>
        </div>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header">Controls</div>
      <div class="card-body">
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" name="allow_edit_after_publish" id="allow_edit_after_publish" value="1" <?= (get_setting('allow_edit_after_publish','0') === '1') ? 'checked' : '' ?>>
          <label class="form-check-label" for="allow_edit_after_publish">Allow editing published exams</label>
        </div>

        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" name="enforce_total_marks" id="enforce_total_marks" value="1" <?= (get_setting('enforce_total_marks','1') === '1') ? 'checked' : '' ?>>
          <label class="form-check-label" for="enforce_total_marks">Enforce total marks consistency</label>
        </div>

        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" name="require_delete_confirmation" id="require_delete_confirmation" value="1" <?= (get_setting('require_delete_confirmation','1') === '1') ? 'checked' : '' ?>>
          <label class="form-check-label" for="require_delete_confirmation">Require confirmation on delete</label>
        </div>
      </div>
    </div>

    <div class="d-flex justify-content-end">
      <button class="btn btn-primary" type="submit">Save Settings</button>
    </div>
  </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
