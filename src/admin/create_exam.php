<?php
require_once '../middleware/auth.php';
requireRole('admin');
?>

<?php require '../constants/header.php' ?>
    <title>Create Exam</title>
</head>
<body class="container mt-4 body">

    <?php
    if (!empty($_SESSION['flash'])): ?>
        <div class="alert alert-info">
            <?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?>
        </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>Exams</h3>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createExamModal">
            + Create Exam
        </button>
    </div>

</body>
</html>
