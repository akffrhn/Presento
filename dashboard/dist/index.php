<?php
session_start();         // ← MUST be first, before any include
include '../../dbcon.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login/index.php');
    exit;
}

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
$stmt = $condb->prepare("
    SELECT fname, email, profilepicture
    FROM USER
    WHERE user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();

$user_info = $stmt->get_result()->fetch_assoc();

$user_email   = $user_info['email'] ?? '';
$user_picture = $user_info['profilepicture'] ?? '';

include 'navigation1.php';   // ← included AFTER session_start + db, so $_SESSION and $condb are available
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard — CYCOM E-Proposal</title>

</head>
<body>

<!-- ══ MAIN ══ -->
<div class="main-wrap">
  <main class="content">

    <!-- Profile header -->
    <div class="profile-header">

    <?php if (!empty($topbar_profile_path)): ?>
        <img src="<?= $topbar_profile_path ?>" alt="Profile Picture"
            class="profile-avatar-img">
    <?php else: ?>
        <div class="profile-avatar">
            <?= strtoupper(substr($user_name, 0, 2)) ?>
        </div>
    <?php endif; ?>

    <div>
        <div class="profile-name"><?= htmlspecialchars(strtoupper($user_name)) ?></div>
        <div class="profile-email"><?= htmlspecialchars($user_email) ?></div>
    </div>

    <a href="/Presento/proposal_submission.php?user_id=<?= urlencode($user_id) ?>" class="btn-new-proposal">
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
            No proposals yet. <a href="/Presento/proposal_submission.php?user_id=<?= urlencode($user_id) ?>" style="color:#4a6cf7;">Submit one!</a>
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
<?php include 'footer.php'; ?>

</div><!-- /main-wrap -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>