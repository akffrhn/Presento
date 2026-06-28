<?php
/* ============================================================
   BOOT — Start session and load dependencies
   session_start() must be first; dbcon.php provides $condb.
   Navigation is included later inside <body> so that the
   <head> CSS loads first in the correct order.
============================================================ */
session_start();
include('dbcon.php');

/* ============================================================
   GUARD — Reject requests that are missing a user_id param
============================================================ */
if (empty($_GET['user_id'])) {
    die("<script>
            alert('Missing user_id');
            window.location.href='dashboard/dist/index.php';
         </script>");
}

/* ============================================================
   FETCH USER — Pull the target user's row from the database
============================================================ */
$current_user_id = $_GET['user_id'];

$query = "SELECT * FROM USER WHERE user_id = ?";
$stmt = $condb->prepare($query);
$stmt->bind_param("s", $current_user_id);
$stmt->execute();

$result = $stmt->get_result();

/* ============================================================
   GUARD — Redirect if no matching user was found
============================================================ */
if ($result->num_rows != 1) {
    die("<script>
            alert('User not found');
            window.location.href='dashboard/dist/index.php';
         </script>");
}

$user = $result->fetch_assoc();

/* ============================================================
   PRE-FETCH profile picture so navigation1.php can reuse it
   for the topbar avatar without an extra DB query
============================================================ */
$user_picture = $user['profilepicture'] ?? '';
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Profile — CYCOM E-Proposal</title>

    <!-- Favicon -->
    <link href="/Presento/assets/img/favicon.png" rel="icon">

    <!-- Bootstrap Icons (used by navigation) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">

    <!-- mainc1.css — dashboard layout, sidebar, topbar -->
    <link rel="stylesheet" href="/Presento/dashboard/dist/assets/css/mainc1.css">

    <!--
        style.css — profile page styles.
        Loaded after mainc1.css so profile form styles
        take priority inside .login-style scope.
        All rules in style.css are scoped under .login-style
        so they don't bleed into the navigation.
    -->
    <link rel="stylesheet" href="/Presento/assets/css/style.css">
</head>

<body>

<!-- ============================================================
     NAVIGATION — sidebar and topbar rendered here inside <body>
     Included after <head> so CSS is already loaded correctly.
     $user_picture is set above so navigation reuses it for the
     topbar avatar without making a second DB query.
============================================================ -->
<?php include('dashboard/dist/navigation1.php'); ?>

<!-- ============================================================
     LAYOUT — main-wrap and content are defined in mainc1.css.
     They provide the left margin (sidebar offset) and top
     padding (topbar offset) so the page content sits correctly.
============================================================ -->
<div class="main-wrap">
    <div class="content">

        <!-- login-style scopes style.css rules to this div only,
             preventing them from affecting the sidebar/topbar -->
        <div class="login-style">

            <!-- Profile card wraps the heading, avatar, and form -->
            <div class="profile-card">

                <h3>Manage Your Profile</h3>
                <p>Manage your personal information and profile picture.</p>

                <!-- ============================================
                     CURRENT AVATAR — only shown if the user
                     already has a profile picture on record
                ============================================ -->
                <?php if(!empty($user['profilepicture'])): ?>
                    <div style="text-align:center; margin-bottom:1.5rem;">
                        <img src="/Presento/assets/profile/<?= htmlspecialchars($user['profilepicture']) ?>"
                             class="profile-preview">
                    </div>
                <?php endif; ?>

                <!-- ============================================
                     FORM — posts to manage-profile-process.php
                     enctype is required for file uploads
                ============================================ -->
                <form action="manage-profile-process.php"
                      method="POST"
                      enctype="multipart/form-data">

                    <!-- Hidden field carries the user_id to the processor -->
                    <input type="hidden"
                           name="current_user_id"
                           value="<?= htmlspecialchars($user['user_id']) ?>">

                    <!-- Profile picture upload -->
                    <div class="form-group">
                        <label>Profile Picture</label>
                        <input type="file" name="picture" accept="image/*" class="form-control">
                    </div>

                    <!-- Student ID (editable but should match DB) -->
                    <div class="form-group">
                        <label>Student ID</label>
                        <input type="text" name="user_id" class="form-control" required
                               value="<?= htmlspecialchars($user['user_id']) ?>">
                    </div>

                    <!-- First name -->
                    <div class="form-group">
                        <label>First Name</label>
                        <input type="text" name="fname" class="form-control" required
                               value="<?= htmlspecialchars($user['fname']) ?>">
                    </div>

                    <!-- Last name -->
                    <div class="form-group">
                        <label>Last Name</label>
                        <input type="text" name="lname" class="form-control" required
                               value="<?= htmlspecialchars($user['lname']) ?>">
                    </div>

                    <!-- Password — left blank means no change in the processor -->
                    <div class="form-group">
                        <label>New Password (Optional)</label>
                        <input type="password" name="password" class="form-control"
                               placeholder="Leave blank to keep current password">
                    </div>

                    <!-- Submit -->
                    <button type="submit" class="btn-login">
                        Update Profile
                    </button>

                </form>

            </div><!-- /profile-card -->

        </div><!-- /login-style -->
    </div><!-- /content -->

    <!-- =========================================================
         FOOTER — must be inside .main-wrap so it sits within the
         sidebar offset, and before </body> so it renders on page
    ========================================================== -->
    <?php include('dashboard/dist/footer.php'); ?>

</div><!-- /main-wrap -->

</body>
</html>