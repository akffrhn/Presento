<?php
session_start();

include('dbcon.php');


if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Original id (from hidden field) used for WHERE clause
    $current_user_id = $_POST['current_user_id'];

    // New id (from editable field) used for SET clause
    $new_user_id = trim($_POST['user_id']);

    $fname = trim($_POST['fname']);
    $lname = trim($_POST['lname']);


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
    // If user_id changes, ensure the new id isn't already taken (since user_id is PRIMARY KEY)
    if (!empty($new_user_id) && $new_user_id !== $current_user_id) {
        $dupCheck = $condb->prepare("SELECT user_id FROM USER WHERE user_id = ? LIMIT 1");
        $dupCheck->bind_param("s", $new_user_id);
        $dupCheck->execute();
        $dupResult = $dupCheck->get_result();
        if ($dupResult && $dupResult->num_rows > 0) {
            die("<script>
                    alert('Student ID already exists. Please use a different ID.');
                    window.history.back();
                 </script>");
        }
        $dupCheck->close();
    }

    // UPDATE including user_id
    $query = "UPDATE USER
              SET user_id = ?,
                  fname = ?,
                  lname = ?";

    $params = [$new_user_id, $fname, $lname];
    $types  = "sss";


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

    $params[] = $current_user_id;
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
        $_SESSION['user_id'] = $new_user_id;


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