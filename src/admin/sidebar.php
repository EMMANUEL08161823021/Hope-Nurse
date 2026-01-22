<style>
    .nav-pills .nav-link.active,
    .nav-pills .show > .nav-link {
    background-color: #eab32e;
    color: #fff;               /* black text for better contrast on gold */
    border-color: #e0a827;     /* subtle border tone */
    }

    .nav-pills .nav-link.active:hover,
    .nav-pills .nav-link.active:focus,
    .nav-pills .show > .nav-link:hover,
    .nav-pills .show > .nav-link:focus {
    background-color: #d79c1f;
    color: #000;
    }

</style>
<nav id="adminSidebar" class="col-12 col-md-3 col-lg-2 px-0 border-end vh-100 collapse d-md-block" style="background-color: #042c2c;">
      <div class="d-flex flex-column p-3 h-100">
        <a href="#" class="d-flex align-items-center mb-3 mb-md-4 text-decoration-none">
          <img src="https://www.hopenurse.com/photos/Original%20logo%20NBG.png" alt="Hope" style="height:45px; margin-right:8px;">
        </a>

        <ul class="nav nav-pills flex-column mb-auto">
            <li class="nav-item">
                <a href="dashboard.php" class="nav-link active">
                Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a href="students.php" class="nav-link">Manage Students</a>
            </li>
        </ul>

        <hr> 

        <div class="mt-auto">
          <div class="small text-muted mb-2">Feature plans</div>
          <ul class="list-unstyled" style="color: #eab32e;">
            <li><a href="#" class="text-decoration-none text-white d-block py-1">Settings</a></li>
            <li><a href="#" class="text-decoration-none text-white d-block py-1">Notifications</a></li>
            <li><a href="#" class="text-decoration-none text-white d-block py-1">User Feedback</a></li>
          </ul>

          <div class="mt-3">
            <a href="../auth/logout.php" class="btn btn-outline-danger btn-sm w-100">Logout</a>
          </div>
        </div>
      </div>
    </nav>