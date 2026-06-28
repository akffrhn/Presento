<link href="/Presento/assets/img/favicon.png" rel="icon">
 <link rel="stylesheet" href="dashboard/dist/assets/css/mainc.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<?php
$current_folder = basename(dirname($_SERVER['PHP_SELF']));
$current_page   = basename($_SERVER['PHP_SELF']);

$user_name = $_SESSION['fname'];
$user_role = $_SESSION['role'];
$user_id   = $_SESSION['user_id'];

$unread_count = 0;
if (isset($condb)) {
    $stmt = $condb->prepare("
        SELECT n.is_read
        FROM NOTIFICATION n
        WHERE n.user_id = ?
        ORDER BY n.created_at DESC
        LIMIT 5
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $notif_result = $stmt->get_result();
    while ($row = $notif_result->fetch_assoc()) {
        if (!$row['is_read']) $unread_count++;
    }
}
?>

<div class="sidebar">
    <div class="sidebar-logo">
        <span><span class="com">CYCOM</span></span>
    </div>

    <div class="sidebar-section-label">Main Menu</div>

    <ul class="sidebar-nav">

        <?php if ($user_role == 'cycom'): ?>

            <li>
                <a href="/Presento/dashboard/dist/index.php"
                   class="<?= $current_page == 'index.php' ? 'active' : '' ?>">
                    <i class="bi bi-house-door"></i>
                    Dashboard
                </a>
            </li>
            <li>
                <a href="/Presento/manageprofile.php?user_id=<?= urlencode($user_id) ?>"
                   class="<?= $current_page == 'manageprofile.php' ? 'active' : '' ?>">
                    <i class="bi bi-person"></i>
                    Manage Profile
                </a>
            </li>
            <li>
                <a href="/Presento/proposal_submission.php"
                   class="<?= $current_page == 'proposal-submission.php' ? 'active' : '' ?>">
                    <i class="bi bi-plus-circle"></i>
                    Proposal Submission
                </a>
            </li>
            <li>
                <a href="/Presento/proposal_list.php"
                   class="<?= $current_page == 'proposal-list.php' ? 'active' : '' ?>">
                    <i class="bi bi-file-earmark-text"></i>
                    Proposal List
                </a>
            </li>
            <li>
                <a href="/Presento/user_list.php"
                   class="<?= $current_page == 'user_list.php' ? 'active' : '' ?>">
                    <i class="bi bi-people"></i>
                    User List
                </a>
            </li>

        <?php elseif ($user_role == 'Student'): ?>

            <li>
                <a href="/Presento/dashboard/dist/index.php"
                   class="<?= $current_page == 'index.php' ? 'active' : '' ?>">
                    <i class="bi bi-house-door"></i>
                    Dashboard
                </a>
            </li>
            <li>
                <a href="/Presento/manageprofile.php?user_id=<?= urlencode($user_id) ?>"
                   class="<?= $current_page == 'manageprofile.php' ? 'active' : '' ?>">
                    <i class="bi bi-person"></i>
                    Manage Profile
                </a>
            </li>
            <li>
                <a href="/Presento/proposal-submission.php?user_id=<?= urlencode($user_id) ?>"
                   class="<?= $current_page == 'proposal_submission.php' ? 'active' : '' ?>">
                    <i class="bi bi-plus-circle"></i>
                    Proposal Submission
                </a>
            </li>
            <li>
                <a href="/Presento/proposal-list.php"
                   class="<?= $current_page == 'proposal_list.php' ? 'active' : '' ?>">
                    <i class="bi bi-file-earmark-text"></i>
                    Proposal List
                </a>
            </li>

        <?php endif; ?>

        <li>
            <a href="/Presento/logout.php">
                <i class="bi bi-box-arrow-right"></i>
                Log Out
            </a>
        </li>

    </ul>

    <div class="sidebar-footer">
        CYCOM E-Proposal &copy; <?= date('Y') ?>
    </div>
</div>
