<?php
require_once '../middleware/auth.php';
requireRole('admin');
?>

<!DOCTYPE html>
<html>
<head>
    <title>Create Exam</title>
    <link rel="stylesheet" href="../assets/bootstrap.min.css">
</head>
<body class="container mt-4">

     <h3>Create Exam</h3>

     <form action="store_exam.php" method="POST">
          <div class="mb-3">
               <label>Exam Title</label>
               <input type="text" name="title" class="form-control" required>
          </div>

          <div class="mb-3">
               <label>Description</label>
               <textarea name="description" class="form-control"></textarea>
          </div>

          <div class="mb-3">
               <label>Duration (minutes)</label>
               <input type="number" name="duration" class="form-control" min="1" required>
          </div>

          <div class="mb-3">
               <label>Total Marks</label>
               <input type="number" name="total_marks" class="form-control" min="0" required>
          </div>

          <div class="mb-3">
               <label>Status</label>
               <select name="status" class="form-control">
                    <option value="draft">Draft</option>
                    <option value="in_progress">In Progress</option>
                    <option value="closed">Closed</option>
               </select>
          </div>

          <button class="btn btn-primary">Create Exam</button>
     </form>

</body>
</html>
