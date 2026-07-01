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
    Priority: cropped base64 (from crop modal) > raw file upload
    =====================================
    */
    $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'profile' . DIRECTORY_SEPARATOR;

    // Ensure upload directory exists
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true)) {
        die("<script>
                alert('Failed to create image upload directory.');
                window.history.back();
             </script>");
    }

    $picture = null;

    /* --- Option A: cropped base64 from crop modal --- */
    $cropped_data = trim($_POST['cropped_picture'] ?? '');

    if ($cropped_data !== '') {

        // Validate and extract extension from the data URI header
        if (!preg_match('/^data:image\/(jpeg|jpg|png|gif|webp);base64,/i', $cropped_data, $matches)) {
            die("<script>
                    alert('Invalid image data. Please try again.');
                    window.history.back();
                 </script>");
        }

        $ext        = strtolower($matches[1]) === 'jpeg' ? 'jpg' : strtolower($matches[1]);
        $image_data = base64_decode(preg_replace('/^data:image\/\w+;base64,/', '', $cropped_data));

        if ($image_data === false) {
            die("<script>
                    alert('Failed to decode image. Please try again.');
                    window.history.back();
                 </script>");
        }

        $picture    = 'profile_' . $current_user_id . '_' . time() . '.' . $ext;
        $targetFile = $uploadDir . $picture;

        if (file_put_contents($targetFile, $image_data) === false) {
            die("<script>
                    alert('Failed to save image. Check folder permissions.');
                    window.history.back();
                 </script>");
        }

    /* --- Option B: raw file upload (no crop) --- */
    } elseif (isset($_FILES['picture']) && $_FILES['picture']['error'] == 0) {

        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $file_type     = mime_content_type($_FILES['picture']['tmp_name']);

        if (!in_array($file_type, $allowed_types, true)) {
            die("<script>
                    alert('Invalid file type. Only JPG, PNG, GIF, WEBP are allowed.');
                    window.history.back();
                 </script>");
        }

        $ext        = strtolower(pathinfo($_FILES['picture']['name'], PATHINFO_EXTENSION));
        $picture    = 'profile_' . $current_user_id . '_' . time() . '.' . $ext;
        $targetFile = $uploadDir . $picture;

        if (!move_uploaded_file($_FILES['picture']['tmp_name'], $targetFile)) {
            die("<script>
                    alert('Failed to upload image. Check folder permissions.');
                    window.history.back();
                 </script>");
        }
    }

    // If a new picture was saved, add it to the query and delete the old one
    if ($picture !== null) {

        // Fetch old picture filename to delete it after successful save
        $oldPicStmt = $condb->prepare("SELECT profilepicture FROM USER WHERE user_id = ? LIMIT 1");
        $oldPicStmt->bind_param("s", $current_user_id);
        $oldPicStmt->execute();
        $oldPicResult = $oldPicStmt->get_result()->fetch_assoc();
        $oldPicStmt->close();

        $old_picture = $oldPicResult['profilepicture'] ?? '';

        $query   .= ", profilepicture = ?";
        $params[] = $picture;
        $types   .= "s";
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

        $query   .= ", password = ?";
        $params[] = $hashedPassword;
        $types   .= "s";
    }

    /*
    =====================================
    WHERE CLAUSE
    =====================================
    */
    $query .= " WHERE user_id = ?";

    $params[] = $current_user_id;
    $types   .= "s";

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

        // Delete the old profile picture now that the DB is updated
        if (!empty($old_picture ?? '')) {
            $old_path = $uploadDir . $old_picture;
            if (file_exists($old_path)) {
                unlink($old_path);
            }
        }

        // Update session so topbar avatar reflects changes immediately
        $_SESSION['fname']   = $fname;
        $_SESSION['lname']   = $lname;
        $_SESSION['user_id'] = $new_user_id;

        echo "<script>
                alert('Profile updated successfully.');
                window.location.href='dashboard/dist/index.php';
              </script>";

    } else {

        // If DB update failed, remove the newly uploaded file to avoid orphans
        if (!empty($picture) && file_exists($uploadDir . $picture)) {
            unlink($uploadDir . $picture);
        }

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