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

if ($session_role !== 'CYCOM' || $session_club_role !== 'High Council') {
    die("<script>
            alert('You are not authorized to access this page');
            window.location.href='dashboard/dist/index.php';
         </script>");
}

$errors = [];
$old = [
    'user_id'   => '',
    'fname'     => '',
    'lname'     => '',
    'email'     => '',
    'phonenum'  => '',
    'role'      => '',
    'clubrole'  => '',
];

/* ============================================================
   HANDLE FORM SUBMISSION
============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $old['user_id']  = trim($_POST['user_id'] ?? '');
    $old['fname']    = trim($_POST['fname'] ?? '');
    $old['lname']    = trim($_POST['lname'] ?? '');
    $old['email']    = trim($_POST['email'] ?? '');
    $old['phonenum'] = trim($_POST['phonenum'] ?? '');
    $old['role']     = $_POST['role'] ?? '';
    $old['clubrole'] = $_POST['clubrole'] ?? '';
    $password        = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Basic validation (server-side fallback)
    if ($old['user_id'] === '' || !ctype_digit($old['user_id'])) {
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
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters.";
    }
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }

    // Check user_id uniqueness
    if (empty($errors)) {
        $checkId = $condb->prepare("SELECT user_id FROM user WHERE user_id = ?");
        $checkId->bind_param("i", $old['user_id']);
        $checkId->execute();
        if ($checkId->get_result()->num_rows > 0) {
            $errors[] = "This User ID is already taken.";
        }
        $checkId->close();
    }

    // Check email uniqueness
    if (empty($errors)) {
        $check = $condb->prepare("SELECT user_id FROM user WHERE email = ?");
        $check->bind_param("s", $old['email']);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $errors[] = "This email is already registered.";
        }
        $check->close();
    }

    // Insert new user
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $insert = $condb->prepare(
            "INSERT INTO user (user_id, fname, lname, email, phonenum, password, role, clubrole)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $insert->bind_param(
            "isssssss",
            $old['user_id'],
            $old['fname'],
            $old['lname'],
            $old['email'],
            $old['phonenum'],
            $hashed_password,
            $old['role'],
            $old['clubrole']
        );

        if ($insert->execute()) {
            $insert->close();
            header("Location: user-list.php?flash=user_added");
            exit;
        } else {
            $errors[] = "Failed to create user. Please try again.";
        }
        $insert->close();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Add New User — CYCOM E-Proposal</title>

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

            <h3>Add New User</h3>

            <?php if (!empty($errors)): ?>
                <div class="error-box">
                    <ul>
                        <?php foreach ($errors as $err): ?>
                            <li><?= htmlspecialchars($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="" class="user-form" novalidate id="addUserForm">

                <!-- User ID -->
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

                <!-- Password -->
                <div class="form-row">
                    <label for="password">Password</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        minlength="8"
                        title="Password must be at least 8 characters"
                        required>
                    <div class="hint">Minimum 8 characters.</div>
                </div>

                <!-- Confirm Password -->
                <div class="form-row">
                    <label for="confirm_password">Confirm Password</label>
                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        title="Passwords must match"
                        required>
                </div>

                <div class="btn-row">
                    <a class="btn-cancel" href="user-list.php">Cancel</a>
                    <button type="submit" class="btn-submit">Create User</button>
                </div>

            </form>

        </div><!-- /form-wrap -->
    </div><!-- /content -->

    <?php include('dashboard/dist/footer.php'); ?>

</div><!-- /main-wrap -->

<script>
    const form    = document.getElementById('addUserForm');
    const pw      = document.getElementById('password');
    const cpw     = document.getElementById('confirm_password');

    // Validate confirm password on submit
    form.addEventListener('submit', function (e) {
        // Reset custom validity first
        cpw.setCustomValidity('');

        if (!form.checkValidity()) {
            e.preventDefault();
            form.reportValidity();
            return;
        }

        if (pw.value !== cpw.value) {
            e.preventDefault();
            cpw.setCustomValidity('Passwords do not match.');
            cpw.reportValidity();
        }
    });

    // Clear custom validity when user retypes confirm password
    cpw.addEventListener('input', function () {
        this.setCustomValidity('');
    });
</script>

</body>
</html>