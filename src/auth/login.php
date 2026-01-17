<?php
// src/auth/login.php
session_start();

// If already logged in, send to public home (preserve your original behavior)
if (isset($_SESSION['user'])) {
    header("Location: ../../public/index.php");
    exit;
}
?>
<?php require '../constants/header.php'?>
<title>Login | Hope Nurse Exam</title>
</head>
<body class="container mt-5">

<h3>Login</h3>

<!-- Show login errors (if any) -->
<?php if (!empty($_SESSION['login_error'])): ?>
    <div class="alert alert-danger">
        <?= htmlspecialchars($_SESSION['login_error']) ?>
    </div>
    <?php unset($_SESSION['login_error']); ?>
<?php endif; ?>

<form method="POST" action="register.php">
    <div class="mb-3">
        <label>Email</label>
        <input type="email" name="email" class="form-control" required autofocus>
    </div>

    <div class="mb-3">
        <label>Password</label>
        <input type="password" name="password" class="form-control" required>
    </div>

    <button class="btn btn-primary">Login</button>
</form>

</body>
</html>
