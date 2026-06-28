<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login/index.php');
    exit;
}

include '../../dbcon.php';

$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['fname'];
$user_role = $_SESSION['role'];

// ── Proposal counts by status ────────────────────────────────
if ($user_role == 'Student' || $user_role == 'cycom') {
    $result = $condb->query("SELECT status, COUNT(*) AS cnt FROM PROPOSAL GROUP BY status");
} else {
    $stmt = $condb->prepare("SELECT status, COUNT(*) AS cnt FROM PROPOSAL WHERE user_id = ? GROUP BY status");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
}

$counts = ['Submitted' => 0, 'Under Review' => 0, 'Accepted' => 0, 'Rejected' => 0];
while ($row = $result->fetch_assoc()) {
    $counts[$row['status']] = $row['cnt'];
}
$total = array_sum($counts);

// ── Recent proposals ─────────────────────────────────────────
if ($user_role == 'Student' || $user_role == 'cycom') {
    $recent = $condb->query("
        SELECT p.proposal_id, p.title, p.status, p.date_submitted, u.fname AS submitter
        FROM PROPOSAL p
        JOIN USER u ON u.user_id = p.user_id
        ORDER BY p.date_submitted DESC
        LIMIT 5
    ");
} else {
    $stmt = $condb->prepare("
        SELECT p.proposal_id, p.title, p.status, p.date_submitted, u.fname AS submitter
        FROM PROPOSAL p
        JOIN USER u ON u.user_id = p.user_id
        WHERE p.user_id = ?
        ORDER BY p.date_submitted DESC
        LIMIT 5
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $recent = $stmt->get_result();
}

// ── Notifications ────────────────────────────────────────────
$stmt = $condb->prepare("
    SELECT n.message, n.is_read, n.created_at, p.title AS proposal_title
    FROM NOTIFICATION n
    JOIN PROPOSAL p ON p.proposal_id = n.proposal_id
    WHERE n.user_id = ?
    ORDER BY n.created_at DESC
    LIMIT 5
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$notifs = $stmt->get_result();

$notif_list   = [];
$unread_count = 0;
while ($row = $notifs->fetch_assoc()) {
    $notif_list[] = $row;
    if (!$row['is_read']) $unread_count++;
}

// ── User info ────────────────────────────────────────────────
$stmt = $condb->prepare("SELECT fname, email FROM USER WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_info  = $stmt->get_result()->fetch_assoc();
$user_email = $user_info['email'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard — CYCOM E-Proposal</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">

  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'Segoe UI', system-ui, sans-serif;
      background: #f0f2f5;
      color: #2c3e50;
      display: flex;
      min-height: 100vh;
    }

    /* ── Sidebar ── */
    .sidebar {
      width: 240px;
      background: #1e2a3a;
      color: #fff;
      display: flex;
      flex-direction: column;
      flex-shrink: 0;
      position: fixed;
      top: 0; left: 0; bottom: 0;
      z-index: 100;
    }

    .sidebar-logo {
      padding: 1.2rem 1.4rem;
      border-bottom: 1px solid rgba(255,255,255,.08);
      display: flex;
      align-items: center;
      gap: .6rem;
      font-size: 1.1rem;
      font-weight: 700;
      letter-spacing: -.3px;
    }
    .sidebar-logo span.cy  { color: #4a6cf7; }
    .sidebar-logo span.com { color: #fff; }

    .sidebar-section-label {
      font-size: .65rem;
      text-transform: uppercase;
      letter-spacing: 1px;
      color: rgba(255,255,255,.35);
      padding: 1.2rem 1.4rem .4rem;
    }

    .sidebar-nav { list-style: none; padding: 0; flex: 1; }
    .sidebar-nav a {
      display: flex; align-items: center; gap: .7rem;
      padding: .65rem 1.4rem;
      color: rgba(255,255,255,.65);
      text-decoration: none;
      font-size: .875rem;
      border-left: 3px solid transparent;
      transition: all .15s;
    }
    .sidebar-nav a:hover { color: #fff; background: rgba(255,255,255,.06); }
    .sidebar-nav a.active {
      color: #fff;
      background: rgba(74,108,247,.18);
      border-left-color: #4a6cf7;
    }
    .sidebar-nav a i { font-size: 1rem; width: 18px; text-align: center; }

    .sidebar-footer {
      padding: 1rem 1.4rem;
      border-top: 1px solid rgba(255,255,255,.08);
      font-size: .78rem;
      color: rgba(255,255,255,.3);
    }

    /* ── Main ── */
    .main-wrap {
      margin-left: 240px;
      flex: 1;
      display: flex;
      flex-direction: column;
      min-height: 100vh;
    }

    /* ── Topbar ── */
    .topbar {
      background: #fff;
      border-bottom: 1px solid #e8eaed;
      padding: .75rem 1.8rem;
      display: flex;
      align-items: center;
      gap: 1rem;
      position: sticky;
      top: 0;
      z-index: 90;
    }
    .topbar-title { font-size: .82rem; color: #6c757d; font-weight: 500; }
    .topbar-right {
      margin-left: auto;
      display: flex;
      align-items: center;
      gap: 1rem;
    }
    .topbar-notif-btn {
      position: relative;
      background: none;
      border: none;
      font-size: 1.15rem;
      color: #6c757d;
      cursor: pointer;
      padding: .3rem;
    }
    .topbar-notif-btn .badge-dot {
      position: absolute;
      top: 2px; right: 2px;
      width: 8px; height: 8px;
      background: #e74c3c;
      border-radius: 50%;
      border: 1.5px solid #fff;
    }
    .topbar-user {
      display: flex; align-items: center; gap: .55rem;
      font-size: .82rem; font-weight: 600; color: #2c3e50;
    }
    .topbar-avatar {
      width: 32px; height: 32px;
      border-radius: 50%;
      background: #4a6cf7;
      color: #fff;
      display: flex; align-items: center; justify-content: center;
      font-size: .75rem; font-weight: 700;
    }

    /* ── Content ── */
    .content { padding: 1.8rem; flex: 1; }

    /* ── Profile header ── */
    .profile-header {
      display: flex;
      align-items: center;
      gap: 1rem;
      margin-bottom: 1.6rem;
    }
    .profile-avatar {
      width: 52px; height: 52px;
      border-radius: 50%;
      background: #4a6cf7;
      color: #fff;
      font-size: 1.1rem; font-weight: 700;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
    }
    .profile-name  { font-size: 1.05rem; font-weight: 700; }
    .profile-email { font-size: .8rem; color: #6c757d; margin-top: .1rem; }
    .btn-new-proposal {
      margin-left: auto;
      background: #4a6cf7;
      color: #fff;
      border: none;
      border-radius: 6px;
      padding: .55rem 1.1rem;
      font-size: .83rem;
      font-weight: 600;
      text-decoration: none;
      display: flex; align-items: center; gap: .4rem;
      white-space: nowrap;
    }
    .btn-new-proposal:hover { background: #3a5ce0; color: #fff; }

    /* ── Stat cards ── */
    .stat-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
      gap: 1rem;
      margin-bottom: 1.6rem;
    }
    .stat-card {
      background: #fff;
      border-radius: 10px;
      padding: 1.1rem 1.3rem;
      border: 1px solid #e8eaed;
      display: flex;
      flex-direction: column;
      gap: .5rem;
    }
    .stat-label { font-size: .78rem; color: #6c757d; line-height: 1.3; }
    .stat-row   { display: flex; align-items: center; justify-content: space-between; }
    .stat-value { font-size: 1.6rem; font-weight: 700; color: #1e2a3a; }
    .stat-icon  { font-size: 1.6rem; }
    .icon-blue   { color: #4a6cf7; }
    .icon-orange { color: #f6a623; }
    .icon-green  { color: #27ae60; }
    .icon-red    { color: #e74c3c; }
    .icon-purple { color: #8e44ad; }

    /* ── Bottom panels ── */
    .bottom-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1.2rem;
    }
    @media (max-width: 900px) { .bottom-grid { grid-template-columns: 1fr; } }

    .panel {
      background: #fff;
      border-radius: 10px;
      border: 1px solid #e8eaed;
      padding: 1.2rem 1.4rem;
    }
    .panel-title {
      font-size: .88rem;
      font-weight: 700;
      color: #1e2a3a;
      margin-bottom: 1rem;
      display: flex;
      align-items: center;
      gap: .4rem;
    }
    .panel-title a {
      margin-left: auto;
      font-size: .75rem;
      font-weight: 600;
      color: #4a6cf7;
      text-decoration: none;
    }

    /* Proposals table */
    .prop-table { width: 100%; border-collapse: collapse; }
    .prop-table th {
      font-size: .7rem;
      text-transform: uppercase;
      letter-spacing: .5px;
      color: #aaa;
      padding: .4rem .5rem;
      text-align: left;
      border-bottom: 1px solid #f0f0f0;
    }
    .prop-table td {
      font-size: .82rem;
      padding: .65rem .5rem;
      border-bottom: 1px solid #f7f7f7;
      vertical-align: middle;
    }
    .prop-table tr:last-child td { border-bottom: none; }
    .prop-table .prop-thumb {
      width: 36px; height: 36px;
      background: #f0f2f5;
      border-radius: 6px;
      display: flex; align-items: center; justify-content: center;
      color: #aaa; font-size: .9rem;
      flex-shrink: 0;
    }
    .prop-title-cell { display: flex; align-items: center; gap: .6rem; }
    .prop-title-text { font-weight: 500; color: #1e2a3a; line-height: 1.25; }
    .prop-date { font-size: .73rem; color: #aaa; }
    .btn-view {
      background: #4a6cf7;
      color: #fff;
      border: none;
      border-radius: 5px;
      padding: .3rem .75rem;
      font-size: .74rem;
      font-weight: 600;
      text-decoration: none;
      white-space: nowrap;
    }
    .btn-view:hover { background: #3a5ce0; color: #fff; }

    /* Status badges */
    .status-badge {
      font-size: .69rem; padding: .25em .65em;
      border-radius: 50px; font-weight: 600; white-space: nowrap;
    }
    .s-submitted    { background: #e9ecef; color: #495057; }
    .s-under-review { background: #fff3cd; color: #856404; }
    .s-accepted     { background: #d1e7dd; color: #0f5132; }
    .s-rejected     { background: #f8d7da; color: #842029; }

    /* Notifications */
    .notif-row {
      display: flex; align-items: flex-start; gap: .55rem;
      padding: .6rem 0;
      border-bottom: 1px solid #f3f3f3;
      font-size: .82rem;
    }
    .notif-row:last-child { border-bottom: none; }
    .notif-dot {
      width: 8px; height: 8px;
      border-radius: 50%;
      flex-shrink: 0;
      margin-top: 5px;
    }
    .notif-msg { font-weight: 600; color: #1e2a3a; line-height: 1.3; }
    .notif-msg.read { font-weight: 400; color: #6c757d; }
    .notif-sub { font-size: .72rem; color: #aaa; margin-top: .15rem; }
    .empty-state { text-align: center; color: #aaa; padding: 1.5rem 0; font-size: .84rem; }

    /* Footer */
    .main-footer {
      background: #fff;
      border-top: 1px solid #e8eaed;
      padding: .7rem 1.8rem;
      font-size: .73rem;
      color: #aaa;
      display: flex;
      justify-content: space-between;
    }


    .dropdown-toggle::after {
      font-size: .65rem;
      color: #aaa;
      margin-left: .25rem;
    }

    .dropdown-menu {
      border-radius: 8px;
      border: 1px solid #e8eaed;
    }

    .dropdown-item {
      font-size: .85rem;
      padding: .55rem 1rem;
    }

    @media (max-width: 768px) {
      .sidebar { transform: translateX(-100%); }
      .main-wrap { margin-left: 0; }
      .stat-grid { grid-template-columns: 1fr 1fr; }
    }
  </style>
</head>
<body>

<?php include 'phdashboard.php'; ?>

<!-- ══ MAIN ══ -->
<div class="main-wrap">

  <!-- Topbar -->
  <header class="topbar">
    <span class="topbar-title">
      <?php if ($user_role == 'Student' || $user_role == 'cycom'): ?>
        Student Dashboard
      <?php else: ?>
        Dashboard
      <?php endif; ?>
    </span>
    <div class="topbar-right">
      <button class="topbar-notif-btn" title="Notifications">
        <i class="bi bi-bell"></i>
        <?php if ($unread_count > 0): ?>
        <span class="badge-dot"></span>
        <?php endif; ?>
      </button>
      <div class="dropdown">
        <button class="btn p-0 border-0 bg-transparent dropdown-toggle d-flex align-items-center gap-2"
                type="button"
                data-bs-toggle="dropdown"
                aria-expanded="false">
          <div class="topbar-avatar"><?= strtoupper(substr($user_name, 0, 2)) ?></div>
          <span class="fw-semibold"><?= htmlspecialchars(strtoupper($user_name)) ?></span>
        </button>

        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
          <li>
            <a class="dropdown-item text-danger"
               href="../../logout.php"
               onclick="return confirm('Are you sure you want to logout?')">
              <i class="bi bi-box-arrow-right me-2"></i> Logout
            </a>
          </li>
        </ul>
      </div>
    </div>
  </header>

  <!-- Content -->
  <main class="content">

    <!-- Profile header -->
    <div class="profile-header">
      <div class="profile-avatar"><?= strtoupper(substr($user_name, 0, 2)) ?></div>
      <div>
        <div class="profile-name"><?= htmlspecialchars(strtoupper($user_name)) ?></div>
        <div class="profile-email"><?= htmlspecialchars($user_email) ?></div>
      </div>
      <a href="../../proposal_submission.php" class="btn-new-proposal">
        <i class="bi bi-plus-lg"></i> New Proposal
      </a>
    </div>

    <!-- Stat cards -->
    <div class="stat-grid">
      <div class="stat-card">
        <div class="stat-label">Total Proposals</div>
        <div class="stat-row">
          <div class="stat-value"><?= $total ?></div>
          <i class="bi bi-file-earmark-text stat-icon icon-blue"></i>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Under Review</div>
        <div class="stat-row">
          <div class="stat-value"><?= $counts['Under Review'] ?></div>
          <i class="bi bi-clock stat-icon icon-orange"></i>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Accepted</div>
        <div class="stat-row">
          <div class="stat-value"><?= $counts['Accepted'] ?></div>
          <i class="bi bi-check-circle stat-icon icon-green"></i>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Rejected</div>
        <div class="stat-row">
          <div class="stat-value"><?= $counts['Rejected'] ?></div>
          <i class="bi bi-x-circle stat-icon icon-red"></i>
        </div>
      </div>
      <?php if ($user_role == 'cycom'): ?>
      <div class="stat-card">
        <?php $total_users = $condb->query("SELECT COUNT(*) AS cnt FROM USER")->fetch_assoc()['cnt']; ?>
        <div class="stat-label">Total Users</div>
        <div class="stat-row">
          <div class="stat-value"><?= $total_users ?></div>
          <i class="bi bi-people stat-icon icon-purple"></i>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <!-- Bottom panels -->
    <div class="bottom-grid">

      <!-- Notifications panel -->
      <div class="panel">
        <div class="panel-title">
          <i class="bi bi-bell" style="color:#f6a623;"></i>
          Notifications
          <?php if ($unread_count > 0): ?>
          <span class="badge bg-danger ms-1" style="font-size:.65rem;"><?= $unread_count ?></span>
          <?php endif; ?>
        </div>

        <?php if (empty($notif_list)): ?>
          <div class="empty-state">No notifications.</div>
        <?php else: ?>
          <?php foreach ($notif_list as $n): ?>
          <div class="notif-row">
            <span class="notif-dot" style="background:<?= $n['is_read'] ? '#dee2e6' : '#4a6cf7' ?>;"></span>
            <div>
              <div class="notif-msg <?= $n['is_read'] ? 'read' : '' ?>">
                <?= htmlspecialchars($n['message']) ?>
              </div>
              <div class="notif-sub">
                <?= htmlspecialchars($n['proposal_title']) ?> &middot; <?= date('d M', strtotime($n['created_at'])) ?>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- Recent proposals panel -->
      <div class="panel">
        <div class="panel-title">
          <i class="bi bi-clock-history" style="color:#4a6cf7;"></i>
          Recent Proposals
          <a href="../../proposal_list.php">View All</a>
        </div>

        <?php
        $rows = [];
        while ($p = $recent->fetch_assoc()) { $rows[] = $p; }
        ?>

        <?php if (empty($rows)): ?>
          <div class="empty-state">
            No proposals yet. <a href="../../proposal_submission.php" style="color:#4a6cf7;">Submit one!</a>
          </div>
        <?php else: ?>
        <table class="prop-table">
          <thead>
            <tr>
              <th>Title</th>
              <?php if ($user_role == 'Student' || $user_role == 'cycom'): ?>
              <th>By</th>
              <?php endif; ?>
              <th>Status</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $p):
              $badge = 's-submitted';
              if ($p['status'] == 'Under Review') $badge = 's-under-review';
              if ($p['status'] == 'Accepted')     $badge = 's-accepted';
              if ($p['status'] == 'Rejected')     $badge = 's-rejected';
            ?>
            <tr>
              <td>
                <div class="prop-title-cell">
                  <div class="prop-thumb"><i class="bi bi-file-earmark"></i></div>
                  <div>
                    <div class="prop-title-text"><?= htmlspecialchars($p['title']) ?></div>
                    <div class="prop-date"><?= date('d M Y', strtotime($p['date_submitted'])) ?></div>
                  </div>
                </div>
              </td>
              <?php if ($user_role == 'Student' || $user_role == 'cycom'): ?>
              <td><?= htmlspecialchars($p['submitter']) ?></td>
              <?php endif; ?>
              <td><span class="status-badge <?= $badge ?>"><?= $p['status'] ?></span></td>
              <td>
                <a href="../../proposal_detail.php?id=<?= $p['proposal_id'] ?>" class="btn-view">View</a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>

    </div><!-- /bottom-grid -->

  </main>

  <footer class="main-footer">
    <span>CYCOM E-Proposal</span>
    <span>&copy; <?= date('Y') ?> CYCOM &mdash; UiTM Cawangan Kedah</span>
  </footer>

</div><!-- /main-wrap -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>