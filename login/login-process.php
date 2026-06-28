<?php
session_start();
include('../dbcon.php'); // dbcon.php berada di /Presento/, login-process.php berada di /Presento/login/

// Semak jika borang sudah diisi
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

  // Mengambil data borang
  $user_id  = trim($_POST['user_id']);
  $password = trim($_POST['password']);

  // Cari pengguna berdasarkan user_id dalam jadual USER
  $query = "SELECT * FROM USER WHERE user_id = ?";
  $stmt  = $condb->prepare($query);
  $stmt->bind_param("s", $user_id);
  $stmt->execute();
  $result = $stmt->get_result();

  // Semak jika pengguna wujud
  if ($result->num_rows == 1) {
    $user = $result->fetch_assoc();

    // Sahkan password menggunakan password_verify()
    if (password_verify($password, $user['password'])) {

      // Simpan maklumat pengguna dalam session
      $_SESSION['user_id'] = $user['user_id'];
      $_SESSION['fname']    = $user['fname'];
      $_SESSION['lname']    = $user['lname'];
      $_SESSION['clubrole']    = $user['clubrole'];
      $_SESSION['role']    = $user['role']; // 'cycom' atau 'member'

      $stmt->close();
      $condb->close();

      // Arahkan pengguna ke dashboard — navbar akan bertukar berdasarkan $_SESSION['role']
      header("Location: /Presento/dashboard/dist/index.php");
      exit();

    } else {
      // Password salah
      echo "<script>
        alert('Incorrect password. Please try again!');
        window.location.href='/Presento/login/index.php';
      </script>";
    }

  } else {
    // User ID tidak dijumpai
    echo "<script>
      alert('Student ID not found. Please try again!');
      window.location.href='/Presento/login/index.php';
    </script>";
  }

  $stmt->close();
  $condb->close();

} else {
  // Bukan POST request
  header("Location: /Presento/login/index.php");
}

exit();
?>