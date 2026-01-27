<?php
$currentScript = basename($_SERVER['SCRIPT_NAME'] ?? '');
function nav_link_html(string $href, string $label, string $currentScript): string {
    $file = basename($href);
    $isActive = ($file === $currentScript);
    $class = 'nav-link' . ($isActive ? ' active' : '');
    $aria = $isActive ? ' aria-current="page"' : '';
    return sprintf('<a href="%s" class="%s"%s>%s</a>', htmlspecialchars($href), $class, $aria, htmlspecialchars($label));
}
?>
<style>
.nav-pills .nav-link {
    color: #ffffff;    
    padding: .5rem .75rem;
    border-radius: 6px;
}

.nav-pills .nav-link.active,
.nav-pills .show > .nav-link {
    background-color: #eab32e;
    color: #000 !important;   
    border-color: #d79c1f;
}

.nav-pills .nav-link:hover,
.nav-pills .nav-link:focus {
    background-color: rgba(234,179,46,0.12);
    color: #fff;
    text-decoration: none;
}

.nav-pills .nav-link.active:hover,
.nav-pills .nav-link.active:focus {
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
            <?php echo nav_link_html('dashboard.php', 'Dashboard', $currentScript); ?>
        </li>
        <li class="nav-item">
            <?php echo nav_link_html('students.php', 'Manage Students', $currentScript); ?>
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
