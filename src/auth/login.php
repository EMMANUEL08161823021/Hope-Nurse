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

<div class="container min-vh-100 d-flex align-items-center justify-content-center">
    <div class="card shadow-sm w-100 d-flex flex-row" style="max-width: 920px; height: 550px;">

        <!-- RIGHT: Illustration / Info -->
        <div class="d-none d-md-flex flex-column align-items-center justify-content-center p-4"
             style="flex: 1; background:#f8fafc; border-left:1px solid rgba(0,0,0,0.05);">
            <div class="text-center">
            

                <!-- Lightweight SVG -->
                 <img height="100" src="https://www.hopenurse.com/photos/Original%20logo%20NBG.png" alt= "logo"/>
         
            </div>
        </div>
        <!-- LEFT: Login Form -->
        <div class="card-body p-4 d-flex flex-column justify-content-center" style="flex: 2;">
            <div class="text-left mb-4">
                <h4 class="mb-1" style="color: #eab32e;">Welcome Back</h4>
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
                <label class="form-label text-dark">Email address</label>
                <input
                    type="email"
                    name="email"
                    class="form-control"
                    required
                    autofocus
                >
                </div>

                <div class="mb-3">
                <label class="form-label text-dark">Password</label>
                <input
                    type="password"
                    name="password"
                    class="form-control"
                    required
                >
                </div>


                <div class="d-grid mb-3">
                    <button class="btn" style="background-color: #eab32e;">Login</button>
                </div>

                <p class="text-center small text-muted mb-0">
                    © Hope Nurse
                </p>
            </form>
        </div>


    </div>
</div>

</body>
</html>
