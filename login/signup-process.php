<?php
session_start();
include('../dbcon.php');

// Semak jika borang sudah diisi
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

  // Mengambil data borang
  $user_id  = $_POST['user_id'];
  $fname     = $_POST['fname'];
  $lname     = $_POST['lname'];
  $email    = $_POST['email'];
  $phonenum    = $_POST['phonenum'];
  $password = $_POST['password'];
  $role     = 'student'; // Ditetapkan sebagai student
  $clubrole     = 'Member'; // Ditetapkan sebagai student

  // Hash password sebelum simpan
  $hashed_password = password_hash($password, PASSWORD_BCRYPT);

  // Merekodkan pengguna baru di jadual USER (ikut ERD)
  $query = "INSERT INTO USER (user_id, fname, lname, email, phonenum, password, role,clubrole) VALUES (?, ?, ?, ?, ?, ?, ?)";

  // Jalankan operasi SQL
  $stmt = $condb->prepare($query);
  $stmt->bind_param("ssssssss", $user_id, $fname, $lname, $email, $phonenum, $hashed_password, $role,$clubrole);
  $result = $stmt->execute();

  // Periksa jika query berjaya
  if ($result) {
    $_SESSION['signup_success'] = true;
    echo "<script>
      alert('You have succeeded to sign up! Please login to complete the registration.');
      window.location.href='index.php';
    </script>";
  } else {
    $_SESSION['signup_error'] = true;
    echo "<script>
      alert('Failed to sign up. Please try again!');
      window.location.href='signup.php';
    </script>";
  }

  $stmt->close();
  $condb->close();
  exit();
}
?>