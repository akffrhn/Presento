<?php
session_start();
include('../dbcon.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

  $user_id  = trim($_POST['user_id'] ?? '');
  $fname    = trim($_POST['fname'] ?? '');
  $lname    = trim($_POST['lname'] ?? '');
  $email    = trim($_POST['email'] ?? '');
  $phonenum = trim($_POST['phonenum'] ?? '');
  $password = $_POST['password'] ?? '';
  $confirm_password = $_POST['confirm_password'] ?? '';
  $role     = 'Student';
  $clubrole = 'Member';

  $errors = [];

  // ── Server-side validation ──────────────────────────────────

  if ($user_id === '' || !ctype_digit($user_id)) {
    $errors[] = "Student ID must contain digits only.";
  }
  if ($fname === '') {
    $errors[] = "First name is required.";
  }
  if ($lname === '') {
    $errors[] = "Last name is required.";
  }
  if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "A valid email address is required.";
  }
  if ($phonenum === '' || !ctype_digit($phonenum)) {
    $errors[] = "Phone number must contain digits only.";
  }
  if (strlen($password) < 8) {
    $errors[] = "Password must be at least 8 characters.";
  }
  if ($password !== $confirm_password) {
    $errors[] = "Passwords do not match.";
  }

  // ── Duplicate checks ────────────────────────────────────────

  if (empty($errors)) {

    // Check duplicate Student ID
    $chkId = $condb->prepare("SELECT user_id FROM user WHERE user_id = ?");
    $chkId->bind_param("s", $user_id);
    $chkId->execute();
    if ($chkId->get_result()->num_rows > 0) {
      $errors[] = "This Student ID is already registered.";
    }
    $chkId->close();

    // Check duplicate email
    $chkEmail = $condb->prepare("SELECT user_id FROM user WHERE email = ?");
    $chkEmail->bind_param("s", $email);
    $chkEmail->execute();
    if ($chkEmail->get_result()->num_rows > 0) {
      $errors[] = "This email is already registered.";
    }
    $chkEmail->close();

    // Check duplicate phone number
    $chkPhone = $condb->prepare("SELECT user_id FROM user WHERE phonenum = ?");
    $chkPhone->bind_param("s", $phonenum);
    $chkPhone->execute();
    if ($chkPhone->get_result()->num_rows > 0) {
      $errors[] = "This phone number is already registered.";
    }
    $chkPhone->close();
  }

  // ── If errors, redirect back with messages ──────────────────

  if (!empty($errors)) {
    $_SESSION['signup_errors'] = $errors;
    $_SESSION['signup_old'] = [
      'user_id'  => $user_id,
      'fname'    => $fname,
      'lname'    => $lname,
      'email'    => $email,
      'phonenum' => $phonenum,
    ];
    header("Location: signup.php");
    exit;
  }

  // ── Insert new user ─────────────────────────────────────────

  $hashed_password = password_hash($password, PASSWORD_BCRYPT);

  $stmt = $condb->prepare(
    "INSERT INTO user (user_id, fname, lname, email, phonenum, password, role, clubrole)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
  );
  $stmt->bind_param("ssssssss", $user_id, $fname, $lname, $email, $phonenum, $hashed_password, $role, $clubrole);
  $result = $stmt->execute();

  if ($result) {
    $stmt->close();
    $condb->close();
    echo "<script>
      alert('You have succeeded to sign up! Please login to complete the registration.');
      window.location.href='index.php';
    </script>";
  } else {
    $stmt->close();
    $condb->close();
    $_SESSION['signup_errors'] = ["Failed to sign up. Please try again."];
    header("Location: signup.php");
  }

  exit();
}
?>