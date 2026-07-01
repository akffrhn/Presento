<link href="/Presento/assets/img/favicon.png" rel="icon">
<?php
// Pastikan session sudah dimulakan sebelum navbar dimuatkan
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
$current_folder = basename(dirname($_SERVER['PHP_SELF']));
$current_page   = basename($_SERVER['PHP_SELF']);
?>

<!-- Fonts -->
<link href="https://fonts.googleapis.com" rel="preconnect">
<link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Raleway:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">

<!-- Vendor CSS Files -->
<link href="/Presento/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
<link href="/Presento/assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
<link href="/Presento/assets/vendor/aos/aos.css" rel="stylesheet">
<link href="/Presento/assets/vendor/swiper/swiper-bundle.min.css" rel="stylesheet">

<!-- Main CSS File -->
<link href="/Presento/assets/css/main.css" rel="stylesheet">

<header id="header" class="header d-flex align-items-center sticky-top">

  <div class="container-fluid container-xl position-relative d-flex align-items-center">

    <a href="/Presento/index.php" class="logo d-flex align-items-center me-auto">
      <h1 class="sitename">
        <img src="/Presento/assets/img/logo.png" alt="CYCOM Logo">
      </h1>
      <span></span>
    </a>

    <?php if (!empty($_SESSION['role']) && $_SESSION['role'] == "cycom"): ?>

      <nav id="navmenu" class="navmenu">
        <ul>

          <li>
            <a href="/Presento/index.php#hero"
               class="<?php if ($current_page == 'index.php' && $current_folder != 'login' && $current_folder != 'dist') echo 'active'; ?>">
               Home
            </a>
          </li>

          <li class="dropdown">
            <a href="#">
              <span>Discover Us</span>
              <i class="bi bi-chevron-down toggle-dropdown"></i>
            </a>
            <ul>
              <li><a href="/Presento/index.php#about">About</a></li>
            </ul>
          </li>

          <li><a href="/Presento/dashboard/dist/index.php"
            class="<?php if ($current_folder == 'dist' && $current_page == 'index.php') echo 'active'; ?>">
            Dashboard</a>
          </li>
          <li><a href="/Presento/proposal_submission.php">Proposal Submission</a></li>
          <li><a href="/Presento/proposal_list.php">Proposal List</a></li>
          <li><a href="/Presento/user_list.php">User List</a></li>
          <li><a href="/Presento/logout.php">Log Out</a></li>

        </ul>
        <i class="mobile-nav-toggle d-xl-none bi bi-list"></i>
      </nav>

    <?php elseif (!empty($_SESSION['role']) && $_SESSION['role'] == "Student"): ?>

      <nav id="navmenu" class="navmenu">
        <ul>

          <li>
            <a href="/Presento/index.php#hero"
               class="<?php if ($current_page == 'index.php' && $current_folder != 'login' && $current_folder != 'dist') echo 'active'; ?>">
               Home
            </a>
          </li>

          <li class="dropdown">
            <a href="#">
              <span>Discover Us</span>
              <i class="bi bi-chevron-down toggle-dropdown"></i>
            </a>
            <ul>
              <li><a href="/Presento/index.php#about">About</a></li>
            </ul>
          </li>

          <li>
            <a href="/Presento/dashboard/dist/index.php"
              class="<?php if ($current_folder == 'dist' && $current_page == 'index.php') echo 'active'; ?>">
              Dashboard
            </a>
          </li>
          <li><a href="/Presento/proposal_submission.php">Proposal Submission</a></li>
          <li><a href="/Presento/logout.php">Log Out</a></li>

        </ul>
        <i class="mobile-nav-toggle d-xl-none bi bi-list"></i>
      </nav>

    <?php else: ?>

      <nav id="navmenu" class="navmenu">
        <ul>

          <li>
            <a href="/Presento/index.php#hero"
               class="<?php if ($current_page == 'index.php' && $current_folder != 'login' && $current_folder != 'dist') echo 'active'; ?>">
               Home
            </a>
          </li>

          <li class="dropdown">
            <a href="#">
              <span>Discover Us</span>
              <i class="bi bi-chevron-down toggle-dropdown"></i>
            </a>
            <ul>
              <li><a href="/Presento/index.php#about">About</a></li>
            </ul>
          </li>

          <li>
            <a href="/Presento/login/index.php"
              class="<?php if ($current_folder == 'login' && $current_page != 'signup.php') echo 'active'; ?>">
              Log in
            </a>
          </li>

          <li>
            <a href="/Presento/login/signup.php"
              class="<?php if ($current_page == 'signup.php') echo 'active'; ?>">
              Sign Up
            </a>
          </li>

        </ul>
        <i class="mobile-nav-toggle d-xl-none bi bi-list"></i>
      </nav>

    <?php endif; ?>

  </div>

</header>