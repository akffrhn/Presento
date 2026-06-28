<link href="/Presento/assets/img/favicon.png" rel="icon">
  <link rel="stylesheet" href="/Presento/dashboard/dist/assets/css/mainc1.css">
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

// Fetch $user_picture if the including page hasn't already
if (!isset($user_picture) && isset($condb)) {
    $stmt_pic = $condb->prepare("SELECT profilepicture FROM USER WHERE user_id = ?");
    $stmt_pic->bind_param("i", $user_id);
    $stmt_pic->execute();
    $user_picture = $stmt_pic->get_result()->fetch_assoc()['profilepicture'] ?? '';
}

$topbar_picture = $user_picture ?? '';
$topbar_profile_path = !empty($topbar_picture)
    ? "/Presento/assets/profile/" . htmlspecialchars($topbar_picture)
    : '';
?>


<div class="sidebar">
    <div class="sidebar-logo">
        <span><span class="com"><img src='/Presento/assets/img/favicon.png' style='height:30px; width:30px;'></span>
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
                <a href="/Presento/proposal_submission.php"
                   class="<?= $current_page == 'proposal_submission.php' ? 'active' : '' ?>">
                    <i class="bi bi-plus-circle"></i>
                    Proposal Submission
                </a>
            </li>
            <li>
                <a href="/Presento/proposal_list.php"
                   class="<?= $current_page == 'proposal_list.php' ? 'active' : '' ?>">
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
                <a href="/Presento/proposal_submission.php?user_id=<?= urlencode($user_id) ?>"
                   class="<?= $current_page == 'proposal_submission.php' ? 'active' : '' ?>">
                    <i class="bi bi-plus-circle"></i>
                    Proposal Submission
                </a>
            </li>
            <li>
                <a href="/Presento/proposal_list.php?user_id=<?= urlencode($user_id) ?>"
                   class="<?= $current_page == 'proposal_list.php' ? 'active' : '' ?>">
                    <i class="bi bi-file-earmark-text"></i>
                    Proposal List
                </a>
            </li>

        <?php endif; ?>

    </ul>

    <div class="sidebar-footer">
        CYCOM E-Proposal &copy; <?= date('Y') ?>
    </div>
</div>

<!-- ══ TOPBAR ══ -->
<div class="topbar">
    <span class="topbar-title">
        <?php
        $page_titles = [
            'index.php'               => 'Dashboard',
            'proposal_submission.php' => 'Proposal Submission',
            'proposal_list.php'       => 'Proposal List',
            'user_list.php'           => 'User List',
            'manageprofile.php'       => 'Manage Profile',
        ];
        echo $page_titles[$current_page] ?? 'Dashboard';
        ?>
    </span>

    <div class="topbar-right">

        <!-- Notification bell -->
        <div class="topbar-bell">
            <i class="bi bi-bell"></i>
            <?php if ($unread_count > 0): ?>
            <span class="bell-badge"><?= $unread_count ?></span>
            <?php endif; ?>
        </div>

        <!-- User dropdown -->
        <div class="topbar-user-wrap" id="userDropdownWrap">
            <div class="topbar-user" id="userDropdownBtn">
                <?php if (!empty($topbar_profile_path)): ?>
                    <img src="<?= $topbar_profile_path ?>" class="topbar-avatar-img" alt="Avatar">
                <?php else: ?>
                    <div class="topbar-avatar">
                        <?= strtoupper(substr($user_name, 0, 2)) ?>
                    </div>
                <?php endif; ?>
                <span class="topbar-username"><?= htmlspecialchars(strtoupper($user_name)) ?></span>
                <i class="bi bi-chevron-down topbar-chevron"></i>
            </div>

            <!-- Dropdown menu -->
            <div class="topbar-dropdown" id="userDropdown">
                <div class="dropdown-header">
                    <div class="dropdown-name"><?= htmlspecialchars($user_name) ?></div>
                    <div class="dropdown-role"><?= htmlspecialchars($user_role) ?></div>
                </div>
                <div class="dropdown-divider"></div>
                <a href="/Presento/manageprofile.php?user_id=<?= urlencode($user_id) ?>" class="dropdown-item">
                    <i class="bi bi-person"></i> Manage Profile
                </a>
                <div class="dropdown-divider"></div>
                <a href="/Presento/logout.php" class="dropdown-item dropdown-item-danger">
                    <i class="bi bi-box-arrow-right"></i> Log Out
                </a>
            </div>
        </div>

    </div>
</div>

<script>
(function () {
    var btn      = document.getElementById('userDropdownBtn');
    var dropdown = document.getElementById('userDropdown');
    var chevron  = btn.querySelector('.topbar-chevron');

    btn.addEventListener('click', function (e) {
        e.stopPropagation();
        var open = dropdown.classList.toggle('open');
        chevron.style.transform = open ? 'rotate(180deg)' : '';
    });

    document.addEventListener('click', function () {
        dropdown.classList.remove('open');
        chevron.style.transform = '';
    });

    dropdown.addEventListener('click', function (e) {
        e.stopPropagation();
    });
})();
</script>