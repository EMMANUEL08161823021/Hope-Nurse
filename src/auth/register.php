<?php
// src/auth/register.php
session_start();
require_once '../config/db.php';

$errors = [];
$old = [
    'full_name' => '',
    'email' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // normalize and validate
    $full_name = trim((string)($_POST['full_name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    $old['full_name'] = $full_name;
    $old['email'] = $email;

    if ($full_name === '') {
        $errors[] = 'Full name is required.';
    }
    if ($email === '') {
        $errors[] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format.';
    }
    if ($password === '') {
        $errors[] = 'Password is required.';
    }
    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match.';
    }

    // check if email already exists
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'Email is already registered.';
        }
    }

    // if no errors, insert user
    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, role, status) VALUES (?, ?, ?, 'student', 'active')");
        $stmt->execute([$full_name, $email, $hash]);

        $_SESSION['flash'] = 'Account created successfully. You can now log in.';
        header('Location: login.php');
        exit;
    }
}
?>

<?php require '../constants/header.php'; ?>
<title>Register</title>
</head>

<style>

    .body {
        background: #042c2c;
    }
    .form-control:focus {
        border-color: #eab32e !important;
        box-shadow: 0 0 0 .2rem rgba(234,179,46,0.25) !important;
        outline: none;
    }

    .form-control:focus-visible {
        outline: 2px solid rgba(234,179,46,0.35);
        outline-offset: 2px;
    }
</style>
<body class="body">

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">

            <div class="card shadow-sm">
                <div class="card-body">
                    <h3 class="card-title mb-3 text-center">Register</h3>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $e): ?>
                                    <li><?= htmlspecialchars($e) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" novalidate>
                        <div class="mb-3">
                            <label for="full_name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" required
                                   value="<?= htmlspecialchars($old['full_name']) ?>">
                            <div class="invalid-feedback">Please enter your full name.</div>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" required
                                   value="<?= htmlspecialchars($old['email']) ?>">
                            <div class="invalid-feedback">Please enter a valid email address.</div>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Program</label>
                            <input type="email" class="form-control" id="email" name="email" required
                                   value="<?= htmlspecialchars($old['email']) ?>">
                            <div class="invalid-feedback">Please enter a valid email address.</div>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                            <div class="invalid-feedback">Please enter a password.</div>
                        </div>

                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            <div class="invalid-feedback">Please confirm your password.</div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Register</button>
                    </form>

                    <p class="text-center mt-3">
                        Already have an account? <a href="login.php">Login here</a>.
                    </p>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
// Bootstrap client-side validation + password match
(() => {
  'use strict';
  const forms = document.querySelectorAll('form');
  forms.forEach(form => {
    form.addEventListener('submit', event => {
      const password = document.getElementById('password');
      const confirm = document.getElementById('confirm_password');

      if (!form.checkValidity() || password.value !== confirm.value) {
        event.preventDefault();
        event.stopPropagation();
        if (password.value !== confirm.value) {
          confirm.setCustomValidity('Passwords do not match.');
          confirm.reportValidity();
        }
      } else {
        confirm.setCustomValidity('');
      }

      form.classList.add('was-validated');
    }, false);
  });
})();
</script>

</body>
</html>
