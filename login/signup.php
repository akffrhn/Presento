<?php
#Memulakan fungsi session
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
              <form action="signup-process.php" method="post">
                <div class="form-group first">
                  <label for="user_id">Student ID</label>
                  <input type="text" class="form-control" placeholder="20XXXXXX4" id="user_id" name="user_id">
                </div>
                <div class="form-group">
                  <label for="fname">First Name</label>
                  <input type="text" class="form-control" placeholder="Your First Name" id="fname" name="fname">
                  <br>
                   <label for="lname">Last Name</label>
                  <input type="text" class="form-control" placeholder="Your Last Name" id="lname" name="lname">
                </div>
                <div class="form-group">
                   <label for="phonenum">Phone Number</label>
                  <input type="phonenum" class="form-control" placeholder="01XXXXXXXXX" id="phonenum" name="phonenum">
                </div>
                <div class="form-group">
                  <label for="email">Email</label>
                  <input type="email" class="form-control" placeholder="yourname@example.com" id="email" name="email">
                </div>
                <div class="form-group last mb-3">
                  <label for="password">Password</label>
                  <input type="password" class="form-control" placeholder="Your Password" id="password" name="password">
                </div>
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

    <?php include('../footer.php'); ?>
  </body>
</html>
