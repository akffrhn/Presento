<link href="/Presento/assets/img/favicon.png" rel="icon">
  <link rel="stylesheet" href="/Presento/dashboard/dist/assets/css/mainc1.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<?php
$current_folder = basename(dirname($_SERVER['PHP_SELF']));
$current_page   = basename($_SERVER['PHP_SELF']);

$user_name = $_SESSION['fname'];
$user_role = $_SESSION['role'];
$user_id   = $_SESSION['user_id'];

$unread_count  = 0;
$notifications = [];
if (isset($condb)) {
    // True unread count across ALL notifications, not just the most recent 5
    $countStmt = $condb->prepare("
        SELECT COUNT(*) AS cnt
        FROM notification
        WHERE user_id = ? AND is_read = 0
    ");
    $countStmt->bind_param("i", $user_id);
    $countStmt->execute();
    $unread_count = (int)($countStmt->get_result()->fetch_assoc()['cnt'] ?? 0);
    $countStmt->close();

    // Latest notifications to render inside the bell dropdown
    $notifStmt = $condb->prepare("
        SELECT notif_id, proposal_id, message, is_read, created_at
        FROM notification
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 8
    ");
    $notifStmt->bind_param("i", $user_id);
    $notifStmt->execute();
    $notifications = $notifStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $notifStmt->close();
}

// Fetch $user_picture if the including page hasn't already
if (!isset($user_picture) && isset($condb)) {
    $stmt_pic = $condb->prepare("SELECT profilepicture FROM USER WHERE user_id = ?");
    $stmt_pic->bind_param("i", $user_id);
    $stmt_pic->execute();
    $user_picture = $stmt_pic->get_result()->fetch_assoc()['profilepicture'] ?? '';
}

// Fetch club role for the dropdown header (was previously incorrectly read from
// the notification loop's $row variable, which was stale or undefined)
$user_clubrole = '';
if (isset($condb)) {
    $stmt_role = $condb->prepare("SELECT clubrole FROM USER WHERE user_id = ?");
    $stmt_role->bind_param("i", $user_id);
    $stmt_role->execute();
    $user_clubrole = $stmt_role->get_result()->fetch_assoc()['clubrole'] ?? '';
}

$topbar_picture = $user_picture ?? '';
$topbar_profile_path = !empty($topbar_picture)
    ? "/Presento/assets/profile/" . htmlspecialchars($topbar_picture)
    : '';
?>

<style>
    .topbar-bell-wrap { position: relative; display: inline-flex; align-items: center; }

    .notif-dropdown {
        display: none;
        position: absolute;
        top: calc(100% + 10px);
        right: 0;
        width: 320px;
        max-height: 380px;
        background: #5a1640;
        border: 1px solid rgba(255,255,255,0.2);
        border-radius: 8px;
        box-shadow: 0 8px 24px rgba(0,0,0,0.35);
        z-index: 1000;
        overflow: hidden;
        flex-direction: column;
    }
    .notif-dropdown.open { display: flex; }

    .notif-dropdown-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 16px;
        color: #fff;
        font-weight: 700;
        font-size: 0.9rem;
    }
    .notif-mark-all {
        font-size: 0.76rem;
        font-weight: 600;
        color: #C89DB8;
        text-decoration: none;
    }
    .notif-mark-all:hover { color: #fff; }

    .notif-list { overflow-y: auto; max-height: 320px; }

    .notif-item {
        display: block;
        padding: 10px 16px;
        border-bottom: 1px solid rgba(255,255,255,0.08);
        text-decoration: none;
        color: #f3e8ef;
    }
    .notif-item:hover { background: rgba(255,255,255,0.06); color: #f3e8ef; text-decoration: none; }
    .notif-item.unread { background: rgba(200,157,184,0.12); }
    .notif-item.unread .notif-message { font-weight: 700; color: #fff; }

    .notif-message { font-size: 0.85rem; line-height: 1.4; margin-bottom: 4px; }
    .notif-time { font-size: 0.74rem; color: #d8c4d0; }

    .notif-empty {
        padding: 2rem 1rem;
        text-align: center;
        color: #d8c4d0;
        font-size: 0.85rem;
    }
</style>


<div class="sidebar">
    <div class="sidebar-logo">
        <span><span class="com"><img src='/Presento/assets/img/favicon.png' style='height:30px; width:30px;'></span>
    </div>

    <div class="sidebar-section-label">Main Menu</div>

    <ul class="sidebar-nav">

        <?php if ($user_role == 'CYCOM'): ?>
            <li>
                <a href="/Presento/dashboard/dist/index.php"
                   class="<?= $current_page == 'index.php' ? 'active' : '' ?>">
                    <i class="bi bi-house-door"></i>
                    Dashboard
                </a>
            </li>
          
            <li>
                <a href="/Presento/proposal-submission.php?user_id=<?= urlencode($user_id) ?>"
                   class="<?= $current_page == 'proposal-submission.php' ? 'active' : '' ?>">
                    <i class="bi bi-plus-circle"></i>
                    Proposal Submission
                </a>
            </li>
            <li>
                <a href="/Presento/proposal-list.php?user_id=<?= urlencode($user_id) ?>"
                   class="<?= $current_page == 'proposal-list.php' ? 'active' : '' ?>">
                    <i class="bi bi-file-earmark-text"></i>
                    Proposal List
                </a>
            </li>
            <li>
                <a href="/Presento/user-list.php?user_id=<?= urlencode($user_id) ?>"
                   class="<?= $current_page == 'user-list.php' ? 'active' : '' ?>">
                    <i class="bi bi-people"></i>
                    User List
                </a>
            </li>

            <li>
                <a href="/Presento/remark-search.php?user_id=<?= urlencode($user_id) ?>"
                   class="<?= $current_page == 'remark-search.php' ? 'active' : '' ?>">
                    <i class="bi bi-search"></i>
                    Search Comments
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
                   class="<?= $current_page == 'proposal-submission.php' ? 'active' : '' ?>">
                    <i class="bi bi-plus-circle"></i>
                    Proposal Submission
                </a>
            </li>
            <li>
                <a href="/Presento/proposal-list.php?user_id=<?= urlencode($user_id) ?>"
                   class="<?= $current_page == 'proposal-list.php' ? 'active' : '' ?>">
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
        <div class="topbar-bell-wrap" id="bellDropdownWrap">
            <div class="topbar-bell" id="bellDropdownBtn">
                <i class="bi bi-bell"></i>
                <?php if ($unread_count > 0): ?>
                <span class="bell-badge" id="bellBadge"><?= $unread_count ?></span>
                <?php endif; ?>
            </div>

            <div class="topbar-dropdown notif-dropdown" id="notifDropdown">
                <div class="dropdown-header notif-dropdown-header">
                    <span>Notifications</span>
                    <?php if ($unread_count > 0): ?>
                        <a href="#" id="markAllReadBtn" class="notif-mark-all">Mark all read</a>
                    <?php endif; ?>
                </div>
                <div class="dropdown-divider"></div>

                <div class="notif-list" id="notifList">
                    <?php if (empty($notifications)): ?>
                        <div class="notif-empty">
                            <i class="bi bi-bell-slash" style="font-size:1.3rem;display:block;margin-bottom:6px;"></i>
                            No notifications yet.
                        </div>
                    <?php else: ?>
                        <?php foreach ($notifications as $n): ?>
                            <a href="/Presento/proposal-view.php?id=<?= (int)$n['proposal_id'] ?>"
                               class="notif-item <?= $n['is_read'] ? '' : 'unread' ?>"
                               data-notif-id="<?= (int)$n['notif_id'] ?>">
                                <div class="notif-message"><?= htmlspecialchars($n['message']) ?></div>
                                <div class="notif-time">
                                    <i class="bi bi-clock"></i>
                                    <?= date('d M Y, h:i A', strtotime($n['created_at'])) ?>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
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
                    <div class="dropdown-role"><?= htmlspecialchars($user_role . ' ' . $user_clubrole) ?></div>
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
        dropdown.classList.toggle('open');
        bellDropdown.classList.remove('open');
        var open = dropdown.classList.contains('open');
        chevron.style.transform = open ? 'rotate(180deg)' : '';
    });

    dropdown.addEventListener('click', function (e) {
        e.stopPropagation();
    });

    // ── Notification bell dropdown ──
    var bellBtn      = document.getElementById('bellDropdownBtn');
    var bellDropdown = document.getElementById('notifDropdown');

    bellBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        bellDropdown.classList.toggle('open');
        dropdown.classList.remove('open');
        chevron.style.transform = '';
    });

    bellDropdown.addEventListener('click', function (e) {
        e.stopPropagation();
    });

    document.addEventListener('click', function () {
        dropdown.classList.remove('open');
        bellDropdown.classList.remove('open');
        chevron.style.transform = '';
    });

    // Mark a single notification read as soon as it's clicked (fires before navigation)
    document.querySelectorAll('.notif-item.unread').forEach(function (item) {
        item.addEventListener('click', function () {
            var notifId = this.getAttribute('data-notif-id');
            var data = new URLSearchParams();
            data.append('action', 'mark_one');
            data.append('notif_id', notifId);
            navigator.sendBeacon('/Presento/notification-mark-read.php', data);
        });
    });

    // Mark all read
    var markAllBtn = document.getElementById('markAllReadBtn');
    if (markAllBtn) {
        markAllBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            fetch('/Presento/notification-mark-read.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=mark_all'
            }).then(function () {
                document.querySelectorAll('.notif-item.unread').forEach(function (el) {
                    el.classList.remove('unread');
                });
                var badge = document.getElementById('bellBadge');
                if (badge) badge.remove();
                markAllBtn.remove();
            });
        });
    }
})();
</script>