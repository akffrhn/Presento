<?php
session_start();
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link href="https://fonts.googleapis.com/css?family=Roboto:300,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="fonts/icomoon/style.css">
    <link rel="stylesheet" href="css/owl.carousel.min.css">
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="css/style.css">
    <link href="/Presento/assets/img/favicon.png" rel="icon">
    <title>Sign Up</title>
  </head>
  <body>
    <?php include('../navbar.php'); ?>

    <div class="d-lg-flex half">
      <div class="contents order-2 order-md-1">
        <div class="container">
          <div class="row align-items-center justify-content-center">
            <div class="col-md-7">
              <h3>Sign Up to <strong>CYCOM E-Proposal Event System</strong></h3>
              <p class="mb-4">Fill in your details below to create an account.</p>

              <?php if (!empty($_SESSION['signup_errors'])): ?>
                <div class="alert alert-danger">
                  <ul class="mb-0">
                    <?php foreach ($_SESSION['signup_errors'] as $err): ?>
                      <li><?= htmlspecialchars($err) ?></li>
                    <?php endforeach; ?>
                  </ul>
                </div>
                <?php unset($_SESSION['signup_errors']); ?>
              <?php endif; ?>

              <form action="signup-process.php" method="post" novalidate id="signupForm">
                <div class="form-group first">
                  <label for="user_id">Student ID</label>
                  <input
                    type="text"
                    class="form-control"
                    placeholder="20XXXXXX4"
                    id="user_id"
                    name="user_id"
                    value="<?= htmlspecialchars($_SESSION['signup_old']['user_id'] ?? '') ?>"
                    pattern="[0-9]+"
                    title="Student ID must contain digits only"
                    required>
                </div>

                <div class="form-group">
                  <label for="fname">First Name</label>
                  <input
                    type="text"
                    class="form-control"
                    placeholder="Your First Name"
                    id="fname"
                    name="fname"
                    value="<?= htmlspecialchars($_SESSION['signup_old']['fname'] ?? '') ?>"
                    title="First name is required"
                    required>
                  <br>
                  <label for="lname">Last Name</label>
                  <input
                    type="text"
                    class="form-control"
                    placeholder="Your Last Name"
                    id="lname"
                    name="lname"
                    value="<?= htmlspecialchars($_SESSION['signup_old']['lname'] ?? '') ?>"
                    title="Last name is required"
                    required>
                </div>

                <div class="form-group">
                  <label for="phonenum">Phone Number</label>
                  <input
                    type="tel"
                    class="form-control"
                    placeholder="01XXXXXXXXX"
                    id="phonenum"
                    name="phonenum"
                    value="<?= htmlspecialchars($_SESSION['signup_old']['phonenum'] ?? '') ?>"
                    pattern="[0-9]+"
                    title="Phone number must contain digits only"
                    required>
                </div>

                <div class="form-group">
                  <label for="email">Email</label>
                  <input
                    type="email"
                    class="form-control"
                    placeholder="yourname@example.com"
                    id="email"
                    name="email"
                    value="<?= htmlspecialchars($_SESSION['signup_old']['email'] ?? '') ?>"
                    title="Please enter a valid email address"
                    required>
                </div>

                <div class="form-group last mb-3">
                  <label for="password">Password</label>
                  <input
                    type="password"
                    class="form-control"
                    placeholder="Your Password (min. 8 characters)"
                    id="password"
                    name="password"
                    minlength="8"
                    title="Password must be at least 8 characters"
                    required>
                </div>

                <div class="form-group last mb-3">
                  <label for="confirm_password">Confirm Password</label>
                  <input
                    type="password"
                    class="form-control"
                    placeholder="Re-enter your password"
                    id="confirm_password"
                    name="confirm_password"
                    title="Passwords must match"
                    required>
                </div>

                <?php unset($_SESSION['signup_old']); ?>

                <input type="submit" value="Sign Up" class="btn btn-block btn-login">
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>

    <script src="js/jquery-3.3.1.min.js"></script>
    <script src="js/popper.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/main.js"></script>

    <script>
      const form = document.getElementById('signupForm');
      const pw   = document.getElementById('password');
      const cpw  = document.getElementById('confirm_password');

      form.addEventListener('submit', function (e) {
        cpw.setCustomValidity('');

        if (!form.checkValidity()) {
          e.preventDefault();
          form.reportValidity();
          return;
        }

        if (pw.value !== cpw.value) {
          e.preventDefault();
          cpw.setCustomValidity('Passwords do not match.');
          cpw.reportValidity();
        }
      });

      cpw.addEventListener('input', function () {
        this.setCustomValidity('');
      });
    </script>

    <?php include('../footer.php'); ?>
  </body>
</html>