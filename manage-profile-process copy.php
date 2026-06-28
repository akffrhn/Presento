<?php
session_start();

include('dbcon.php');


if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $user_id = $_POST['user_id'];
    $fname   = trim($_POST['fname']);
    $lname   = trim($_POST['lname']);

    // Validation
    if (empty($fname) || empty($lname)) {
        die("<script>
                alert('Please complete all required fields.');
                window.history.back();
             </script>");
    }

    /*
    =====================================
    UPDATE WITHOUT PASSWORD / PICTURE
    =====================================
    */
    $query = "UPDATE USER
              SET fname = ?,
                  lname = ?";

    $params = [$fname, $lname];
    $types  = "ss";


    /*
    =====================================
    UPDATE PICTURE IF PROVIDED
    =====================================
    */
    if (
        isset($_FILES['picture']) &&
        $_FILES['picture']['error'] == 0
    ) {

        $picture = time() . "_" . basename($_FILES['picture']['name']);

        // Use absolute path so it works regardless of PHP working directory
        $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'profile' . DIRECTORY_SEPARATOR;
        $targetFile = $uploadDir . $picture;

        // Ensure directory exists
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true)) {
            die("<script>
                    alert('Failed to create image upload directory.');
                    window.history.back();
                 </script>");
        }

        if (move_uploaded_file($_FILES['picture']['tmp_name'], $targetFile)) {

            $query .= ", profilepicture = ?";
            $params[] = $picture;
            $types .= "s";
        }
    }

    /*
    =====================================
    UPDATE PASSWORD IF PROVIDED
    =====================================
    */
    if (!empty($_POST['password'])) {

        $hashedPassword = password_hash(
            $_POST['password'],
            PASSWORD_DEFAULT
        );

        $query .= ", password = ?";
        $params[] = $hashedPassword;
        $types .= "s";
    }

    /*
    =====================================
    WHERE CLAUSE
    =====================================
    */
    $query .= " WHERE user_id = ?";

    $params[] = $user_id;
    $types .= "s";

    /*
    =====================================
    EXECUTE QUERY
    =====================================
    */
    $stmt = $condb->prepare($query);

    $stmt->bind_param(
        $types,
        ...$params
    );

    if ($stmt->execute()) {

        // Update session name if current user edits own profile
        $_SESSION['fname'] = $fname;
        $_SESSION['lname'] = $lname;
        $_SESSION['user_id'] = $user_id;

        echo "<script>
                alert('Profile updated successfully.');
                 window.location.href='dashboard/dist/index.php'
              </script>";

    } else {

        echo "<script>
                alert('Profile update failed.');
                window.history.back();
              </script>";
    }

    $stmt->close();
    $condb->close();

} else {

    header("Location: dashboard/dist/index.php");
    exit();
}
?>