<?php 
// src/auth/login.php
session_start();

// If already logged in, send to public home (preserve original behavior)
if (isset($_SESSION['user'])) {
    header("Location: ../../public/index.php");
    exit;
}
?>
<?php require '../constants/header.php'?>
<title>Login | Hope Nurse Exam</title>
</head>

<body class="bg-light">

<div class="container min-vh-100 d-flex align-items-center justify-content-center">
    <div class="card shadow-sm w-100 d-flex flex-row" style="max-width: 920px;">

        <!-- LEFT: Login Form -->
        <div class="card-body p-4 d-flex flex-column justify-content-center" style="flex: 1;">
            <div class="text-center mb-4">
                <h4 class="mb-1">Welcome Back</h4>
                <p class="text-muted small mb-0">Login to continue your Hope journey</p>
            </div>

            <!-- Show login errors (if any) -->
            <?php if (!empty($_SESSION['login_error'])): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($_SESSION['login_error']) ?>
                </div>
                <?php unset($_SESSION['login_error']); ?>
            <?php endif; ?>

            <form method="POST" action="register.php">
                <div class="mb-3">
                    <label class="form-label">Email address</label>
                    <input
                        type="email"
                        name="email"
                        class="form-control"
                        placeholder="you@example.com"
                        required
                        autofocus
                    >
                </div>

                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input
                        type="password"
                        name="password"
                        class="form-control"
                        placeholder="Enter your password"
                        required
                    >
                </div>

                <div class="d-grid mb-3">
                    <button class="btn btn-primary">Login</button>
                </div>

                <p class="text-center small text-muted mb-0">
                    © Hope Nurse
                </p>
            </form>
        </div>

        <!-- RIGHT: Illustration / Info -->
        <div class="d-none d-md-flex flex-column align-items-center justify-content-center p-4"
             style="flex: 1; background:#f8fafc; border-left:1px solid rgba(0,0,0,0.05);">
            <div class="text-center">
                <h5 class="mb-2">Welcome to Hope</h5>
                <p class="small text-muted mb-3">
                    Prepare for NCLEX, track progress, and apply for the Cradle Program.
                </p>

                <!-- Lightweight SVG -->
                <svg width="160" height="120" viewBox="0 0 160 120" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <rect x="4" y="4" width="152" height="112" rx="8" fill="#fff" stroke="#e9ecef"/>
                    <path d="M20 40h120v6H20zM20 60h80v6H20zM20 80h100v6H20z" fill="#ced4da"/>
                </svg>
            </div>
        </div>

    </div>
</div>

</body>
</html>
