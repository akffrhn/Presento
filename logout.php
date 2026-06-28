<?PHP
# memulakan fungsi session
session_start();

# menghapuskan nilai pemboleh ubah
session_unset();

#menghapuskan fungsi session
session_destroy();

echo"<script>window.location.href='index.php';</script>";
?>