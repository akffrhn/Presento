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
    <title>Log in</title>
  </head>
  <body>
    <?php include('../navbar.php'); ?>

    <div class="d-lg-flex half">
      <div class="contents order-2 order-md-1">
        <div class="container">
          <div class="row align-items-center justify-content-center">
            <div class="col-md-7">
              <h3>Login to <strong>CYCOM E-Proposal Event System</strong></h3>
              <p class="mb-4">Make sure to put your Student ID and password correctly.</p>
              <form action="login-process.php" method="post">
                <div class="form-group first">
                  <label for="user_id">Student ID</label>
                  <input type="text" class="form-control" placeholder="20XXXXXX4" id="user_id" name="user_id">
                </div>
                <div class="form-group last mb-3">
                  <label for="password">Password</label>
                  <input type="password" class="form-control" placeholder="Your Password" id="password" name="password">
                </div>
                <div class="d-flex mb-5 align-items-center">
                  <label class="control control--checkbox mb-0"><span class="caption">Remember me</span>
                    <input type="checkbox" checked="checked"/>
                    <div class="control__indicator"></div>
                  </label>
                  <span class="ml-auto"><a href="#" class="forgot-pass">Forgot Password</a></span>
                </div>
                <input type="submit" value="Log In" class="btn btn-block btn-login">
              </form>
              <p class="mt-3 text-center">Don't have an account? <a href="signup.php">Sign Up</a></p>
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