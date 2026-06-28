<?php
session_start();
include('dbcon.php');

/* ============================================================
   AUTH CHECKS — must be logged in, CYCOM role, High Council
============================================================ */
if (empty($_SESSION['user_id'])) {
    die("<script>
            alert('Please log in');
            window.location.href='dashboard/dist/index.php';
         </script>");
}

$session_role      = $_SESSION['role'] ?? '';
$session_club_role = $_SESSION['clubrole'] ?? '';
$current_user_id   = (int) $_SESSION['user_id'];

if ($session_role !== 'CYCOM' || $session_club_role !== 'High Council') {
    die("<script>
            alert('You are not authorized to access this page');
            window.location.href='dashboard/dist/index.php';
         </script>");
}

/* ============================================================
   WHICH USER ARE WE EDITING?
   - On GET (link from user-list): identified by ?user_id=...
   - On POST (form resubmit): identified by the hidden
     'current_user_id' field, since 'user_id' itself is now an
     EDITABLE field that may hold a brand-new value.
============================================================ */
$target_user_id = (int) ($_GET['user_id'] ?? $_POST['current_user_id'] ?? 0);

if ($target_user_id <= 0) {
    die("<script>
            alert('Missing user_id');
            window.location.href='user-list.php';
         </script>");
}

$errors = [];
$old = [
    'user_id'        => $target_user_id,
    'fname'          => '',
    'lname'          => '',
    'email'          => '',
    'phonenum'       => '',
    'role'           => '',
    'clubrole'       => '',
    'profilepicture' => '',
];

/* ============================================================
   LOAD EXISTING USER (used to pre-fill the form on GET,
   and as a fallback / existence check on POST)
============================================================ */
function get_user(mysqli $condb, int $user_id): ?array {
    $q = "SELECT user_id, fname, lname, email, phonenum, role, clubrole, profilepicture FROM user WHERE user_id = ?";
    $s = $condb->prepare($q);
    $s->bind_param("i", $user_id);
    $s->execute();
    return $s->get_result()->fetch_assoc();
}

$existing_user = get_user($condb, $target_user_id);

if (!$existing_user) {
    die("<script>
            alert('User not found');
            window.location.href='user-list.php';
         </script>");
}

// Pre-fill from the database record by default
$old['fname']          = $existing_user['fname'];
$old['lname']          = $existing_user['lname'];
$old['email']          = $existing_user['email'];
$old['phonenum']       = $existing_user['phonenum'];
$old['role']           = $existing_user['role'];
$old['clubrole']       = $existing_user['clubrole'];
$old['profilepicture'] = $existing_user['profilepicture'];

/* ============================================================
   HANDLE FORM SUBMISSION
============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $new_user_id      = trim($_POST['user_id'] ?? '');
    $old['user_id']   = $new_user_id !== '' ? $new_user_id : $target_user_id;
    $old['fname']    = trim($_POST['fname'] ?? '');
    $old['lname']    = trim($_POST['lname'] ?? '');
    $old['email']    = trim($_POST['email'] ?? '');
    $old['phonenum'] = trim($_POST['phonenum'] ?? '');
    $old['role']     = $_POST['role'] ?? '';
    $old['clubrole'] = $_POST['clubrole'] ?? '';
    $password         = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $change_password  = ($password !== '' || $confirm_password !== '');
    $user_id_changed   = ($new_user_id !== (string) $target_user_id);

    // Basic validation (server-side fallback)
    if ($new_user_id === '' || !ctype_digit($new_user_id)) {
        $errors[] = "A valid numeric User ID is required.";
    }
    if ($old['fname'] === '') {
        $errors[] = "First name is required.";
    }
    if ($old['lname'] === '') {
        $errors[] = "Last name is required.";
    }
    if ($old['email'] === '' || !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "A valid email is required.";
    }
    if ($old['phonenum'] === '' || !ctype_digit($old['phonenum'])) {
        $errors[] = "A valid phone number (digits only) is required.";
    }
    if (!in_array($old['role'], ['Student', 'CYCOM'], true)) {
        $errors[] = "Please select a valid role.";
    }
    if (!in_array($old['clubrole'], ['High Council', 'Exco', 'Member'], true)) {
        $errors[] = "Please select a valid club role.";
    }

    // Password is optional on update — only validate if the admin is changing it
    if ($change_password) {
        if (strlen($password) < 8) {
            $errors[] = "Password must be at least 8 characters.";
        }
        if ($password !== $confirm_password) {
            $errors[] = "Passwords do not match.";
        }
    }

    // If the User ID is changing, make sure the new one isn't already taken
    // (user_id is the PRIMARY KEY)
    if (empty($errors) && $user_id_changed) {
        $dupCheck = $condb->prepare("SELECT user_id FROM user WHERE user_id = ? LIMIT 1");
        $dupCheck->bind_param("i", $new_user_id);
        $dupCheck->execute();
        if ($dupCheck->get_result()->num_rows > 0) {
            $errors[] = "This User ID is already taken. Please use a different ID.";
        }
        $dupCheck->close();
    }

    // Check email uniqueness, excluding this user's own record
    if (empty($errors)) {
        $check = $condb->prepare("SELECT user_id FROM user WHERE email = ? AND user_id != ?");
        $check->bind_param("si", $old['email'], $target_user_id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $errors[] = "This email is already registered to another user.";
        }
        $check->close();
    }

    /*
    =====================================
    HANDLE PROFILE PICTURE UPLOAD (optional)
    =====================================
    */
    $new_picture_filename = null;

    if (empty($errors) && isset($_FILES['picture']) && $_FILES['picture']['error'] !== UPLOAD_ERR_NO_FILE) {

        if ($_FILES['picture']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "There was a problem uploading the picture. Please try again.";
        } else {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $finfo         = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type     = finfo_file($finfo, $_FILES['picture']['tmp_name']);

            $max_size = 5 * 1024 * 1024; // 5 MB

            if (!in_array($mime_type, $allowed_types, true)) {
                $errors[] = "Profile picture must be a JPG, PNG, GIF, or WEBP image.";
            } elseif ($_FILES['picture']['size'] > $max_size) {
                $errors[] = "Profile picture must be smaller than 5MB.";
            } else {
                $new_picture_filename = time() . "_" . basename($_FILES['picture']['name']);

                // Absolute path so it works regardless of PHP's working directory
                $uploadDir  = __DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'profile' . DIRECTORY_SEPARATOR;
                $targetFile = $uploadDir . $new_picture_filename;

                if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true)) {
                    $errors[] = "Failed to create image upload directory.";
                } elseif (!move_uploaded_file($_FILES['picture']['tmp_name'], $targetFile)) {
                    $errors[] = "Failed to save the uploaded picture. Please try again.";
                    $new_picture_filename = null;
                }
            }
        }
    }

    // Update the user
    if (empty($errors)) {

        // Build the SET clause dynamically so password/picture only get
        // touched when the admin actually provided new ones.
        $set_parts = ['user_id = ?', 'fname = ?', 'lname = ?', 'email = ?', 'phonenum = ?', 'role = ?', 'clubrole = ?'];
        $params    = [$new_user_id, $old['fname'], $old['lname'], $old['email'], $old['phonenum'], $old['role'], $old['clubrole']];
        $types     = 'issssss';

        if ($new_picture_filename !== null) {
            $set_parts[] = 'profilepicture = ?';
            $params[]    = $new_picture_filename;
            $types      .= 's';
        }

        if ($change_password) {
            $set_parts[] = 'password = ?';
            $params[]    = password_hash($password, PASSWORD_DEFAULT);
            $types      .= 's';
        }

        $params[] = $target_user_id;
        $types   .= 'i';

        $query  = "UPDATE user SET " . implode(', ', $set_parts) . " WHERE user_id = ?";
        $update = $condb->prepare($query);
        $update->bind_param($types, ...$params);

        if ($update->execute()) {
            $update->close();

            // If the admin just edited their OWN account, keep the session in sync
            if ($current_user_id === $target_user_id) {
                $_SESSION['user_id']  = (int) $new_user_id;
                $_SESSION['fname']    = $old['fname'];
                $_SESSION['lname']    = $old['lname'];
                $_SESSION['role']     = $old['role'];
                $_SESSION['clubrole'] = $old['clubrole'];
            }

            header("Location: user-list.php?flash=user_updated");
            exit;
        } else {
            $errors[] = "Failed to update user. Please try again.";
            $update->close();
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Update User — CYCOM E-Proposal</title>

    <link href="/Presento/assets/img/favicon.png" rel="icon">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/Presento/dashboard/dist/assets/css/mainc1.css">
    <link rel="stylesheet" href="/Presento/assets/css/style.css">

    <style>
        body {
            background: #491231;
        }

        .form-wrap {
            padding: 1.5rem;
            max-width: 600px;
        }

        .form-wrap h3 {
            color: #fff;
            font-weight: 700;
            margin-bottom: 1.25rem;
        }

        .user-form {
            background: #5a1640;
            border: 2px solid #fff;
            border-radius: 8px;
            padding: 2rem;
        }

        .form-row {
            margin-bottom: 1.1rem;
        }

        .form-row label {
            display: block;
            color: #fff;
            font-weight: 600;
            margin-bottom: 6px;
            font-size: 0.9rem;
        }

        .form-row input,
        .form-row select {
            width: 100%;
            padding: 8px 10px;
            border-radius: 4px;
            border: 1px solid #ccc;
            font-family: Arial, sans-serif;
            font-size: 0.95rem;
            box-sizing: border-box;
        }

        .form-row input:disabled {
            background: #e9e3e7;
            color: #777;
            cursor: not-allowed;
        }

        .current-picture {
            display: block;
            width: 72px;
            height: 72px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid #fff;
            margin-bottom: 8px;
        }

        .form-row input[type="file"] {
            padding: 6px;
            background: #fff;
        }

        .form-row-split {
            display: flex;
            gap: 12px;
        }

        .form-row-split .form-row {
            flex: 1;
        }

        .btn-row {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 1.5rem;
        }

        .btn-cancel,
        .btn-submit {
            padding: 9px 20px;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            font-size: 0.95rem;
        }

        .btn-submit {
            background: #fff;
            color: #491231;
        }

        .btn-cancel {
            background: transparent;
            color: #fff;
            border: 1px solid #fff;
        }

        .error-box {
            background: #C89DB8;
            color: #491231;
            padding: 12px 16px;
            border-radius: 4px;
            margin-bottom: 1rem;
        }

        .error-box ul {
            margin: 0;
            padding-left: 1.2rem;
        }

        .hint {
            color: #d8c4d0;
            font-size: 0.8rem;
            margin-top: 4px;
        }
    </style>
</head>

<body>

<?php include('dashboard/dist/navigation1.php'); ?>

<div class="main-wrap">
    <div class="content">

        <div class="form-wrap">

            <h3>Update User</h3>

            <?php if (!empty($errors)): ?>
                <div class="error-box">
                    <ul>
                        <?php foreach ($errors as $err): ?>
                            <li><?= htmlspecialchars($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="" class="user-form" enctype="multipart/form-data" novalidate id="updateUserForm">

                <input type="hidden" name="current_user_id" value="<?= htmlspecialchars($target_user_id) ?>">

                <!-- User ID (editable — checked for uniqueness before saving) -->
                <div class="form-row">
                    <label for="user_id">User ID</label>
                    <input
                        type="number"
                        id="user_id"
                        name="user_id"
                        value="<?= htmlspecialchars($old['user_id']) ?>"
                        min="1"
                        step="1"
                        placeholder="e.g. 1001"
                        title="User ID must be a positive number"
                        required>
                    <div class="hint">Changing this updates the user's ID everywhere it's stored. Make sure it isn't already in use.</div>
                </div>

                <!-- Profile Picture (optional) -->
                <div class="form-row">
                    <label for="picture">Profile Picture</label>
                    <?php if (!empty($old['profilepicture'])): ?>
                        <img
                            src="assets/profile/<?= htmlspecialchars($old['profilepicture']) ?>"
                            alt="Current profile picture"
                            class="current-picture">
                    <?php endif; ?>
                    <input
                        type="file"
                        id="picture"
                        name="picture"
                        accept="image/jpeg,image/png,image/gif,image/webp">
                    <div class="hint">Leave blank to keep the current picture. JPG, PNG, GIF, or WEBP, max 5MB.</div>
                </div>

                <!-- First & Last Name -->
                <div class="form-row-split">
                    <div class="form-row">
                        <label for="fname">First Name</label>
                        <input
                            type="text"
                            id="fname"
                            name="fname"
                            value="<?= htmlspecialchars($old['fname']) ?>"
                            placeholder="e.g. Ali"
                            title="First name is required"
                            required>
                    </div>
                    <div class="form-row">
                        <label for="lname">Last Name</label>
                        <input
                            type="text"
                            id="lname"
                            name="lname"
                            value="<?= htmlspecialchars($old['lname']) ?>"
                            placeholder="e.g. Hassan"
                            title="Last name is required"
                            required>
                    </div>
                </div>

                <!-- Email -->
                <div class="form-row">
                    <label for="email">Email</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="<?= htmlspecialchars($old['email']) ?>"
                        placeholder="e.g. ali@example.com"
                        title="Please enter a valid email address"
                        required>
                </div>

                <!-- Phone -->
                <div class="form-row">
                    <label for="phonenum">Phone Number</label>
                    <input
                        type="tel"
                        id="phonenum"
                        name="phonenum"
                        value="<?= htmlspecialchars($old['phonenum']) ?>"
                        placeholder="e.g. 0123456789"
                        pattern="[0-9]+"
                        title="Phone number must contain digits only"
                        required>
                </div>

                <!-- Role & Club Role -->
                <div class="form-row-split">
                    <div class="form-row">
                        <label for="role">Role</label>
                        <select id="role" name="role" required title="Please select a role">
                            <option value="" disabled <?= $old['role'] === '' ? 'selected' : '' ?>>Select role</option>
                            <option value="Student" <?= $old['role'] === 'Student' ? 'selected' : '' ?>>Student</option>
                            <option value="CYCOM"   <?= $old['role'] === 'CYCOM'   ? 'selected' : '' ?>>CYCOM</option>
                        </select>
                    </div>
                    <div class="form-row">
                        <label for="clubrole">Club Role</label>
                        <select id="clubrole" name="clubrole" required title="Please select a club role">
                            <option value="" disabled <?= $old['clubrole'] === '' ? 'selected' : '' ?>>Select club role</option>
                            <option value="High Council" <?= $old['clubrole'] === 'High Council' ? 'selected' : '' ?>>High Council</option>
                            <option value="Exco"         <?= $old['clubrole'] === 'Exco'         ? 'selected' : '' ?>>Exco</option>
                            <option value="Member"       <?= $old['clubrole'] === 'Member'       ? 'selected' : '' ?>>Member</option>
                        </select>
                    </div>
                </div>

                <!-- Password (optional on update) -->
                <div class="form-row">
                    <label for="password">New Password</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        minlength="8"
                        title="Leave blank to keep the current password, or enter at least 8 characters">
                    <div class="hint">Leave both password fields blank to keep the current password.</div>
                </div>

                <!-- Confirm Password -->
                <div class="form-row">
                    <label for="confirm_password">Confirm New Password</label>
                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        title="Passwords must match">
                </div>

                <div class="btn-row">
                    <a class="btn-cancel" href="user-list.php">Cancel</a>
                    <button type="submit" class="btn-submit">Save Changes</button>
                </div>

            </form>

        </div><!-- /form-wrap -->
    </div><!-- /content -->

    <?php include('dashboard/dist/footer.php'); ?>

</div><!-- /main-wrap -->

<script>
    const form = document.getElementById('updateUserForm');
    const pw   = document.getElementById('password');
    const cpw  = document.getElementById('confirm_password');

    form.addEventListener('submit', function (e) {
        // Reset custom validity first
        pw.setCustomValidity('');
        cpw.setCustomValidity('');

        if (!form.checkValidity()) {
            e.preventDefault();
            form.reportValidity();
            return;
        }

        // Only enforce password rules if the admin actually typed something
        if (pw.value !== '' || cpw.value !== '') {
            if (pw.value.length < 8) {
                e.preventDefault();
                pw.setCustomValidity('Password must be at least 8 characters.');
                pw.reportValidity();
                return;
            }
            if (pw.value !== cpw.value) {
                e.preventDefault();
                cpw.setCustomValidity('Passwords do not match.');
                cpw.reportValidity();
            }
        }
    });

    // Clear custom validity when user retypes either password field
    pw.addEventListener('input', function () {
        this.setCustomValidity('');
    });
    cpw.addEventListener('input', function () {
        this.setCustomValidity('');
    });
</script>

</body>
</html>