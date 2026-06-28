<?php
# Menyemak nilai pemboleh ubah session['role']
if (!isset($_SESSION['role']) || ($_SESSION['role'] != "member" && $_SESSION['role'] != "cycom")) {
    # jika pemboleh ubah tidak wujud atau nilainya tidak sama dengan pembeli atau staff, aturcara akan dihentikan
    die("<script>alert('Please Login First'); window.location.href='/Presento/login/index.php';</script>");
}
?>
