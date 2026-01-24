<?php
// src/auth/register.php
session_start();

$errors = $_SESSION['register_errors'] ?? [];
$old    = $_SESSION['register_old'] ?? [
    'full_name' => '',
    'email'     => '',
    'country'   => '',
    'program'   => 'Hope Prep'
];

unset($_SESSION['register_errors'], $_SESSION['register_old']);

$allowedPrograms = ['Hope Prep', 'DIY', 'Cradle'];
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

                    <form method="POST" action="register_post.php" novalidate>

                        <div class="mb-3">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="full_name" class="form-control"
                                   value="<?= htmlspecialchars($old['full_name']) ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control"
                                   value="<?= htmlspecialchars($old['email']) ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Country</label>
                            <input type="text" name="country" class="form-control"
                                   value="<?= htmlspecialchars($old['country']) ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Program</label>
                            <select name="program" class="form-select" required>
                                <?php foreach ($allowedPrograms as $p): ?>
                                    <option value="<?= $p ?>" <?= $old['program'] === $p ? 'selected' : '' ?>>
                                        <?= $p ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required minlength="8">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Confirm Password</label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>

                        <button class="btn btn-warning w-100">Create Account</button>
                    </form>

                    <p class="text-center mt-3">
                        Already registered? <a href="login.php">Login</a>
                    </p>

                </div>
            </div>

        </div>
    </div>
</div>

</body>
</html>
