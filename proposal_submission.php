<?php
session_start();
include('dbcon.php');

if (empty($_GET['user_id'])) {
    die("<script>
            alert('Missing user_id');
            window.location.href='dashboard/dist/index.php';
         </script>");
}

$current_user_id = $_GET['user_id'];

$query = "SELECT * FROM USER WHERE user_id = ?";
$stmt = $condb->prepare($query);
$stmt->bind_param("s", $current_user_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows != 1) {
    die("<script>
            alert('User not found');
            window.location.href='dashboard/dist/index.php';
         </script>");
}

$user = $result->fetch_assoc();
$user_picture = $user['profilepicture'] ?? '';
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Submit Proposal — CYCOM E-Proposal</title>

    <link href="/Presento/assets/img/favicon.png" rel="icon">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/Presento/dashboard/dist/assets/css/mainc1.css">
    <link rel="stylesheet" href="/Presento/assets/css/style.css">
</head>

<body>

<?php include('dashboard/dist/navigation1.php'); ?>

<div class="main-wrap">
    <div class="content">

        <div class="login-style">

            <div class="profile-card">

                <h3>Submit a Proposal</h3>
                <p>Fill in the details below to submit your proposal for review.</p>

                <form action="proposal-process.php"
                      method="POST">

                    <!-- Hidden field carries the user_id to the processor -->
                    <input type="hidden"
                           name="user_id"
                           value="<?= htmlspecialchars($user['user_id']) ?>">

                    <!-- Title -->
                    <div class="form-group">
                        <label>Proposal Title</label>
                        <input type="text" name="title" class="form-control" required maxlength="200">
                    </div>

                    <!-- Objectives -->
                    <div class="form-group">
                        <label>Objectives</label>
                        <textarea name="objectives" class="form-control" rows="4" required></textarea>
                    </div>

                    <!-- Activities -->
                    <div class="form-group">
                        <label>Activities</label>
                        <textarea name="activities" class="form-control" rows="4" required></textarea>
                    </div>

                    <!-- Submit -->
                    <button type="submit" class="btn-login">
                        Submit Proposal
                    </button>

                </form>

            </div><!-- /profile-card -->

        </div><!-- /login-style -->
    </div><!-- /content -->

    <?php include('dashboard/dist/footer.php'); ?>

</div><!-- /main-wrap -->

</body>
</html>