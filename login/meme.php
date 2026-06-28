<?php
// ── Session & auth guard ──────────────────────────────────────────────────────
session_start();

// Redirect unauthenticated visitors to login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login/index.php');
    exit;
}

// ── Database connection ───────────────────────────────────────────────────────
include '../../dbcon.php';

// ── Session variables ─────────────────────────────────────────────────────────
$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['fname'];
$user_role = $_SESSION['role'];

// ── Proposal counts by status ─────────────────────────────────────────────────
// cycom and Students see ALL proposals; other roles see only their own
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

// ── Recent proposals (latest 5) ───────────────────────────────────────────────
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

// ── Notifications (latest 5 for this user) ────────────────────────────────────
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

// ── Total users count (cycom admin only) ──────────────────────────────────────
$total_users = 0;
if ($user_role == 'cycom') {
    $total_users = $condb->query("SELECT COUNT(*) AS cnt FROM USER")->fetch_assoc()['cnt'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard — CYCOM E-Proposal</title>

  <!-- navbar.php injects: fonts, Bootstrap CSS/JS, sidebar HTML, topbar HTML, and sidebar styles -->
  <?php include '../../navbar.php'; ?>

  <!-- ══════════════════════════════════════════════════════════════════════
       DASHBOARD-SPECIFIC STYLES
       ══════════════════════════════════════════════════════════════════════ -->
  <style>
    /* ── Stat cards ──────────────────────────────────────────────────────── */
    .stat-card {
      background: rgba(255,255,255,.08);
      border: 1px solid rgba(255,255,255,.10);
      border-radius: 14px;
      padding: 1.2rem 1.1rem;
      display: flex;
      align-items: center;
      gap: 1rem;
      transition: transform .15s, box-shadow .15s;
    }
    .stat-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(0,0,0,.25);
    }
    .stat-icon {
      width: 48px; height: 48px;
      border-radius: 12px;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.4rem;
      flex-shrink: 0;
    }
    .stat-label { font-size: .7rem; opacity: .7; text-transform: uppercase; letter-spacing: .06em; }
    .stat-value { font-size: 1.7rem; font-weight: 700; line-height: 1.1; }

    /* Stat icon colour variants */
    .ic-blue   { background: rgba(74,108,247,.25); color: #7b9cff; }
    .ic-grey   { background: rgba(108,117,125,.25); color: #adb5bd; }
    .ic-orange { background: rgba(246,166,35,.25);  color: #ffc859; }
    .ic-green  { background: rgba(39,174,96,.25);   color: #5ddb8e; }
    .ic-red    { background: rgba(231,76,60,.25);   color: #ff7f7f; }
    .ic-purple { background: rgba(142,68,173,.25);  color: #c579f7; }

    /* ── Content panels (white cards) ────────────────────────────────────── */
    .panel {
      background: #fff;
      border-radius: 14px;
      padding: 1.3rem 1.4rem;
      box-shadow: 0 2px 12px rgba(0,0,0,.10);
    }
    .panel-title {
      font-size: .88rem;
      font-weight: 700;
      color: #2c3e50;
      margin-bottom: 1rem;
      display: flex;
      align-items: center;
      gap: .45rem;
    }

    /* ── Welcome banner ──────────────────────────────────────────────────── */
    .welcome-banner {
      background: rgba(255,255,255,.06);
      border: 1px solid rgba(255,255,255,.09);
      border-radius: 14px;
      padding: 1.2rem 1.5rem;
      margin-bottom: 1.5rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      flex-wrap: wrap;
    }
    .welcome-banner h4 { font-weight: 700; margin: 0; font-size: 1.1rem; }
    .welcome-banner p  { font-size: .8rem; opacity: .75; margin: .2rem 0 0; }

    /* ── Proposal table ──────────────────────────────────────────────────── */
    .dash-table th { font-size: .7rem; text-transform: uppercase; color: #aaa; border-top: none; }
    .dash-table td { font-size: .83rem; vertical-align: middle; }

    /* ── Status badges ───────────────────────────────────────────────────── */
    .status-badge {
      font-size: .7rem;
      padding: .28em .75em;
      border-radius: 50px;
      font-weight: 600;
      white-space: nowrap;
    }
    .s-submitted    { background: #e9ecef; color: #495057; }
    .s-under-review { background: #fff3cd; color: #856404; }
    .s-accepted     { background: #d1e7dd; color: #0f5132; }
    .s-rejected     { background: #f8d7da; color: #842029; }

    /* ── Notifications ───────────────────────────────────────────────────── */
    .notif-row {
      padding: .6rem 0;
      border-bottom: 1px solid #f0f0f0;
      font-size: .82rem;
      display: flex;
      align-items: flex-start;
      gap: .5rem;
    }
    .notif-row:last-child { border-bottom: none; }
    .notif-dot {
      width: 9px; height: 9px;
      border-radius: 50%;
      flex-shrink: 0;
      margin-top: 4px;
    }

    /* ── Quick-action buttons ────────────────────────────────────────────── */
    .quick-btn {
      display: flex;
      align-items: center;
      gap: .6rem;
      width: 100%;
      padding: .65rem .9rem;
      border-radius: 10px;
      font-size: .83rem;
      font-weight: 500;
      margin-bottom: .5rem;
      text-decoration: none;
      transition: opacity .15s;
    }
    .quick-btn:hover { opacity: .88; }
    .quick-btn i { font-size: 1rem; }
  </style>
</head>

<!-- ══════════════════════════════════════════════════════════════════════════
     PAGE BODY
     .layout-shell = sidebar (fixed) + .main-content (scrollable right side)
     ══════════════════════════════════════════════════════════════════════════ -->
<body>
<div class="layout-shell">

  <!-- Sidebar and topbar are already injected by navbar.php above -->

  <!-- ════════════════════════════════════════════════════════════════════════
       MAIN CONTENT
       ════════════════════════════════════════════════════════════════════════ -->
  <main class="main-content">
    <div class="main-inner">

      <!-- ── Welcome banner ─────────────────────────────────────────────── -->
      <div class="welcome-banner">
        <div>
          <h4>Welcome, <?= htmlspecialchars($user_name) ?>!</h4>
          <p>
            <?php if ($user_role == 'cycom'): ?>
              CYCOM Admin · E-Proposal System
            <?php else: ?>
              Student Dashboard · CYCOM E-Proposal System
            <?php endif; ?>
          </p>
        </div>

        <!-- Quick "New Proposal" CTA -->
        <a href="/Presento/proposal_submission.php"
           class="btn btn-sm"
           style="background:#c0185a;color:#fff;border-radius:8px;font-size:.8rem;padding:.45rem .9rem">
          <i class="bi bi-plus-lg me-1"></i> New Proposal
        </a>
      </div>

      <!-- ── Stat cards row ──────────────────────────────────────────────── -->
      <div class="row g-3 mb-4">

        <!-- Total proposals -->
        <div class="col-6 col-md-4 col-lg-2">
          <div class="stat-card">
            <div class="stat-icon ic-blue"><i class="bi bi-file-earmark-text"></i></div>
            <div>
              <div class="stat-label">Total</div>
              <div class="stat-value"><?= $total ?></div>
            </div>
          </div>
        </div>

        <!-- Submitted -->
        <div class="col-6 col-md-4 col-lg-2">
          <div class="stat-card">
            <div class="stat-icon ic-grey"><i class="bi bi-send"></i></div>
            <div>
              <div class="stat-label">Submitted</div>
              <div class="stat-value"><?= $counts['Submitted'] ?></div>
            </div>
          </div>
        </div>

        <!-- Under Review -->
        <div class="col-6 col-md-4 col-lg-2">
          <div class="stat-card">
            <div class="stat-icon ic-orange"><i class="bi bi-hourglass-split"></i></div>
            <div>
              <div class="stat-label">Under Review</div>
              <div class="stat-value"><?= $counts['Under Review'] ?></div>
            </div>
          </div>
        </div>

        <!-- Accepted -->
        <div class="col-6 col-md-4 col-lg-2">
          <div class="stat-card">
            <div class="stat-icon ic-green"><i class="bi bi-check-circle"></i></div>
            <div>
              <div class="stat-label">Accepted</div>
              <div class="stat-value"><?= $counts['Accepted'] ?></div>
            </div>
          </div>
        </div>

        <!-- Rejected -->
        <div class="col-6 col-md-4 col-lg-2">
          <div class="stat-card">
            <div class="stat-icon ic-red"><i class="bi bi-x-circle"></i></div>
            <div>
              <div class="stat-label">Rejected</div>
              <div class="stat-value"><?= $counts['Rejected'] ?></div>
            </div>
          </div>
        </div>

        <?php if ($user_role == 'cycom'): ?>
        <!-- Total users — cycom admin only -->
        <div class="col-6 col-md-4 col-lg-2">
          <div class="stat-card">
            <div class="stat-icon ic-purple"><i class="bi bi-people"></i></div>
            <div>
              <div class="stat-label">Users</div>
              <div class="stat-value"><?= $total_users ?></div>
            </div>
          </div>
        </div>
        <?php endif; ?>

      </div><!-- /stat cards -->

      <!-- ── Main panels row ─────────────────────────────────────────────── -->
      <div class="row g-4">

        <!-- ── Recent Proposals table ──────────────────────────────────── -->
        <div class="col-lg-8">
          <div class="panel">
            <div class="panel-title">
              <i class="bi bi-clock-history text-primary"></i>
              Recent Proposals
              <!-- Link to full proposal list -->
              <a href="/Presento/proposal_list.php"
                 class="ms-auto btn btn-sm btn-outline-primary"
                 style="font-size:.72rem">View All</a>
            </div>

            <div class="table-responsive">
              <table class="table dash-table">
                <thead>
                  <tr>
                    <th>Title</th>
                    <?php if ($user_role == 'Student' || $user_role == 'cycom'): ?>
                    <th>Submitted By</th>
                    <?php endif; ?>
                    <th>Status</th>
                    <th>Date</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  $has_rows = false;
                  while ($p = $recent->fetch_assoc()):
                    $has_rows = true;

                    // Map status to CSS badge class
                    $badge = 's-submitted';
                    if ($p['status'] == 'Under Review') $badge = 's-under-review';
                    if ($p['status'] == 'Accepted')     $badge = 's-accepted';
                    if ($p['status'] == 'Rejected')     $badge = 's-rejected';
                  ?>
                  <tr>
                    <td><?= htmlspecialchars($p['title']) ?></td>

                    <?php if ($user_role == 'Student' || $user_role == 'cycom'): ?>
                    <td><?= htmlspecialchars($p['submitter']) ?></td>
                    <?php endif; ?>

                    <td><span class="status-badge <?= $badge ?>"><?= $p['status'] ?></span></td>
                    <td class="text-muted"><?= date('d M Y', strtotime($p['date_submitted'])) ?></td>
                    <td>
                      <a href="/Presento/proposal_detail.php?id=<?= $p['proposal_id'] ?>"
                         class="btn btn-sm btn-light border">View</a>
                    </td>
                  </tr>
                  <?php endwhile; ?>

                  <?php if (!$has_rows): ?>
                  <!-- Empty state -->
                  <tr>
                    <td colspan="5" class="text-center text-muted py-4">
                      No proposals yet. <a href="/Presento/proposal_submission.php">Submit one!</a>
                    </td>
                  </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div><!-- /recent proposals -->

        <!-- ── Side column: Notifications + Quick Actions ──────────────── -->
        <div class="col-lg-4">

          <!-- Notifications panel -->
          <div class="panel mb-4">
            <div class="panel-title">
              <i class="bi bi-bell text-warning"></i>
              Notifications
              <?php if ($unread_count > 0): ?>
              <!-- Unread badge count -->
              <span class="badge bg-danger ms-1"><?= $unread_count ?></span>
              <?php endif; ?>
            </div>

            <?php if (empty($notif_list)): ?>
              <!-- Empty notifications state -->
              <p class="text-muted text-center mb-0" style="font-size:.82rem">No notifications.</p>

            <?php else: ?>
              <?php foreach ($notif_list as $n): ?>
              <div class="notif-row">
                <!-- Blue dot = unread, grey = read -->
                <span class="notif-dot" style="background:<?= $n['is_read'] ? '#dee2e6' : '#4a6cf7' ?>"></span>
                <div>
                  <div class="<?= $n['is_read'] ? 'text-muted' : 'fw-semibold' ?>" style="color:#2c3e50">
                    <?= htmlspecialchars($n['message']) ?>
                  </div>
                  <small class="text-muted">
                    <?= htmlspecialchars($n['proposal_title']) ?> · <?= date('d M', strtotime($n['created_at'])) ?>
                  </small>
                </div>
              </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>

          <!-- Quick Actions panel -->
          <div class="panel">
            <div class="panel-title">
              <i class="bi bi-lightning-charge text-warning"></i>
              Quick Actions
            </div>

            <!-- Submit a new proposal -->
            <a href="/Presento/proposal_submission.php"
               class="quick-btn text-white"
               style="background:#4a6cf7">
              <i class="bi bi-file-earmark-plus"></i> Submit Proposal
            </a>

            <!-- View all proposals -->
            <a href="/Presento/proposal_list.php"
               class="quick-btn text-white"
               style="background:#27ae60">
              <i class="bi bi-list-check"></i> View All Proposals
            </a>

            <?php if ($user_role == 'cycom'): ?>
            <!-- Admin-only: manage users -->
            <a href="/Presento/user_list.php"
               class="quick-btn text-white"
               style="background:#8e44ad">
              <i class="bi bi-people"></i> Manage Users
            </a>
            <?php endif; ?>

          </div>

        </div><!-- /side column -->

      </div><!-- /main panels row -->

    </div><!-- /.main-inner -->
  </main><!-- /.main-content -->

</div><!-- /.layout-shell -->

<?php include '../../footer.php'; ?>

</body>
</html>