<?php
# memulakan fungsi session
session_start();

# ID tetap untuk akaun placeholder "Deleted User" (lihat setup_deleted_user.sql)
define('DELETED_USER_ID', 0);

# menyemak kewujudan data GET user_id
if (!empty($_GET['user_id']))
{
    # memanggil fail connection.php
    include('dbcon.php');

    $user_id = (int) $_GET['user_id'];

    # jangan benarkan padam akaun placeholder itu sendiri
    if ($user_id === DELETED_USER_ID)
    {
        echo "<script>alert('This account cannot be deleted.');
        window.location.href='user-list.php';</script>";
        exit;
    }

    # langkah 1: pindahkan semua proposal milik user ini kepada placeholder
    $reassign = "UPDATE proposal SET user_id = ? WHERE user_id = ?";
    $stmt_reassign = mysqli_prepare($condb, $reassign);
    $deleted_id = DELETED_USER_ID;
    mysqli_stmt_bind_param($stmt_reassign, "ii", $deleted_id, $user_id);
    mysqli_stmt_execute($stmt_reassign);

    # langkah 2: padam user sebenar
    $arahan = "DELETE FROM user WHERE user_id = ?";
    $stmt = mysqli_prepare($condb, $arahan);
    mysqli_stmt_bind_param($stmt, "i", $user_id);

    if (mysqli_stmt_execute($stmt))
    {
        echo "<script>alert('Data was deleted ! Their proposals were reassigned to a Deleted User account.');
        window.location.href='user-list.php';</script>";
    }
    else
    {
        echo "<script>alert('Data can\\'t be deleted :(');
        window.location.href='user-list.php';</script>";
    }
}
else
{
    # jika data GET tidak wujud (empty). papar popup dan buka fail user-list.php
    die("<script>alert('Error : Direct access!');
    window.location.href='user-list.php';</script>");
}
?>