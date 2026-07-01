<?php
session_start();
include('dbcon.php');

/* ============================================================
   AUTH CHECK
============================================================ */
if (empty($_SESSION['user_id'])) {
    die("<script>
            alert('Please log in');
            window.location.href='dashboard/dist/index.php';
         </script>");
}

// Get proposal_id from URL
$proposal_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($proposal_id <= 0) {
    die("<script>alert('Invalid proposal.'); window.location.href='proposal-list.php';</script>");
}

$session_user_id  = (int)$_SESSION['user_id'];
$session_role     = $_SESSION['role'] ?? '';
$session_clubrole = $_SESSION['clubrole'] ?? '';

/* ============================================================
   FETCH PROPOSAL + SUBMITTER
============================================================ */
$stmt = $condb->prepare("
    SELECT p.*, u.fname, u.lname, u.email, u.profilepicture
    FROM proposal p
    JOIN user u ON p.user_id = u.user_id
    WHERE p.proposal_id = ?
");
$stmt->bind_param("i", $proposal_id);
$stmt->execute();
$proposal = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$proposal) {
    die("<script>alert('Proposal not found.'); window.location.href='proposal-list.php';</script>");
}

// Only allow: the submitter themselves, or CYCOM members
if ((int)$proposal['user_id'] !== $session_user_id && $session_role !== 'CYCOM') {
    die("<script>alert('You are not authorized to view this proposal.'); window.location.href='dashboard/dist/index.php';</script>");
}

$is_cycom        = ($session_role === 'CYCOM');
$is_high_council = ($session_clubrole === 'High Council');

/* ============================================================
   AUTO-MOVE TO "Under Review" WHEN A CYCOM MEMBER OPENS IT
   Triggers on both 'Submitted' (first submission) and
   'Resubmitted' (owner revised after rejection).
============================================================ */
if ($is_cycom && in_array($proposal['status'], ['Submitted', 'Resubmitted'], true)) {
    $newStatus = 'Under Review';

    $upd = $condb->prepare("UPDATE proposal SET status = ? WHERE proposal_id = ?");
    $upd->bind_param("si", $newStatus, $proposal_id);
    $upd->execute();
    $upd->close();

    // Keep in-memory copy in sync so the page reflects it immediately
    $proposal['status'] = $newStatus;
}

/* ============================================================
   FETCH STATUS LOG / COMMENTS
============================================================ */
$logStmt = $condb->prepare("
    SELECT psl.*, u.fname, u.lname, u.clubrole, u.profilepicture
    FROM proposal_status_log psl
    JOIN user u ON psl.reviewed_by = u.user_id
    WHERE psl.proposal_id = ?
    ORDER BY psl.changed_at DESC
");
$logStmt->bind_param("i", $proposal_id);
$logStmt->execute();
$logs = $logStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$logStmt->close();

/* ============================================================
   STATUS BADGE HELPER
============================================================ */
function statusBadge($status) {
    $map = [
        'Submitted'    => ['color' => '#6c757d', 'icon' => 'bi-send'],
        'Under Review' => ['color' => '#f0a500', 'icon' => 'bi-hourglass-split'],
        'Accepted'     => ['color' => '#28a745', 'icon' => 'bi-check-circle'],
        'Rejected'     => ['color' => '#dc3545', 'icon' => 'bi-x-circle'],
        'Resubmitted'  => ['color' => '#1a6b8a', 'icon' => 'bi-arrow-repeat'],
    ];
    $s = $map[$status] ?? ['color' => '#999', 'icon' => 'bi-circle'];
    return "<span style='background:{$s['color']};color:#fff;padding:4px 12px;border-radius:20px;font-size:0.82rem;font-weight:600;'>
                <i class='bi {$s['icon']}'></i> {$status}
            </span>";
}

// Flash message
$flash = $_GET['flash'] ?? '';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Proposal Details — CYCOM E-Proposal</title>

    <link href="/Presento/assets/img/favicon.png" rel="icon">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/Presento/dashboard/dist/assets/css/mainc1.css">
    <link rel="stylesheet" href="/Presento/assets/css/style.css">

    <style>
        .comment-reviewer-pic {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
            border: 1px solid rgba(255,255,255,0.4);
            margin-right: 8px;
            vertical-align: middle;
        }
        .comment-reviewer-pic-placeholder {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #3c0e26;
            border: 1px solid rgba(255,255,255,0.4);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-right: 8px;
            vertical-align: middle;
            color: #C89DB8;
            font-size: 0.9rem;
        }
        .comment-reviewer {
            display: flex;
            align-items: center;
        }
    </style>
</head>
<body>

<?php include('dashboard/dist/navigation1.php'); ?>

<div class="main-wrap">
    <div class="content">
        <div class="page-wrap">

            <a href="proposal-list.php?user_id=<?= urlencode($session_user_id) ?>" class="btn-back">
                <i class="bi bi-arrow-left"></i> Back to Proposals
            </a>

            <h3>Proposal Details</h3>

            <!-- Flash Messages -->
            <?php if ($flash === 'remark_updated'): ?>
                <div class="flash flash-success"><i class="bi bi-check-circle"></i> Remark updated successfully.</div>
            <?php elseif ($flash === 'remark_deleted'): ?>
                <div class="flash flash-danger"><i class="bi bi-trash"></i> Remark deleted.</div>
            <?php elseif ($flash === 'remark_delete_failed'): ?>
                <div class="flash flash-warning"><i class="bi bi-exclamation-triangle"></i> Failed to delete remark. Please try again.</div>
            <?php elseif ($flash === 'commented'): ?>
                <div class="flash flash-success"><i class="bi bi-chat-left-text"></i> Comment submitted.</div>
            <?php endif; ?>

            <!-- Proposal Card -->
            <div class="proposal-card">

                <div class="proposal-title"><?= htmlspecialchars($proposal['title']) ?></div>

                <div class="proposal-meta">
                    <?= statusBadge($proposal['status']) ?>
                    <span><i class="bi bi-calendar3"></i>
                        <?= date('d M Y, h:i A', strtotime($proposal['date_submitted'])) ?>
                    </span>
                    <span><i class="bi bi-hash"></i> Proposal #<?= $proposal['proposal_id'] ?></span>
                </div>

                <hr class="divider">

                <div class="section-label"><i class="bi bi-bullseye"></i> Objectives</div>
                <div class="section-content"><?= htmlspecialchars($proposal['objectives']) ?></div>

                <div class="section-label"><i class="bi bi-list-check"></i> Activities</div>
                <div class="section-content"><?= htmlspecialchars($proposal['activities']) ?></div>

                <hr class="divider">

                <div class="section-label"><i class="bi bi-person-circle"></i> Submitted By</div>
                <div class="submitter">
                    <?php if (!empty($proposal['profilepicture'])): ?>
                        <img src="/Presento/assets/profile/<?= htmlspecialchars(basename($proposal['profilepicture'])) ?>" alt="Profile">
                    <?php else: ?>
                        <div class="avatar-placeholder">
                            <i class="bi bi-person"></i>
                        </div>
                    <?php endif; ?>
                    <div class="submitter-info">
                        <div class="name">
                            <?= htmlspecialchars($proposal['fname'] . ' ' . $proposal['lname']) ?>
                        </div>
                        <div class="email"><?= htmlspecialchars($proposal['email']) ?></div>
                    </div>
                </div>

            </div>

            <!-- Status Log / Comments -->
            <div class="comments-card">
                <h5><i class="bi bi-chat-left-text"></i> Review Comments
                    <span style="color:#C89DB8;font-weight:400;font-size:0.85rem;margin-left:8px;">
                        (<?= count($logs) ?> <?= count($logs) === 1 ? 'entry' : 'entries' ?>)
                    </span>
                </h5>

                <?php if (empty($logs)): ?>
                    <div class="no-comments">
                        <i class="bi bi-chat-dots" style="font-size:1.5rem;display:block;margin-bottom:6px;"></i>
                        No review comments yet.
                    </div>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <div class="comment-item">
                            <div class="comment-header">
                                <div class="comment-reviewer">
                                    <?php if (!empty($log['profilepicture'])): ?>
                                        <img
                                            src="/Presento/assets/profile/<?= htmlspecialchars(basename($log['profilepicture'])) ?>"
                                            alt="<?= htmlspecialchars($log['fname'] . ' ' . $log['lname']) ?>"
                                            class="comment-reviewer-pic">
                                    <?php else: ?>
                                        <span class="comment-reviewer-pic-placeholder">
                                            <i class="bi bi-person"></i>
                                        </span>
                                    <?php endif; ?>
                                    <?= htmlspecialchars($log['fname'] . ' ' . $log['lname']) ?>
                                    <span><?= htmlspecialchars($log['clubrole']) ?></span>
                                </div>
                                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                                    <?= statusBadge($log['status']) ?>
                                    <span class="comment-date">
                                        <i class="bi bi-clock"></i>
                                        <?= date('d M Y, h:i A', strtotime($log['changed_at'])) ?>
                                    </span>
                                </div>
                            </div>

                            <?php if (!empty($log['remarks'])): ?>
                                <div class="comment-remarks">
                                    <i class="bi bi-chat-quote" style="color:#C89DB8;margin-right:4px;"></i>
                                    <?= htmlspecialchars($log['remarks']) ?>
                                </div>
                            <?php else: ?>
                                <div class="comment-remarks" style="color:#d8c4d0;font-style:italic;">
                                    No remarks provided.
                                </div>
                            <?php endif; ?>

                            <!-- Edit / Delete buttons -->
                            <?php $is_author = ((int)$log['reviewed_by'] === $session_user_id); ?>
                            <?php if ($is_author || $is_high_council): ?>
                                <div class="comment-actions">
                                    <?php if ($is_author): ?>
                                        <a href="remark-edit.php?log_id=<?= $log['log_id'] ?>" class="btn-edit-remark">
                                            <i class="bi bi-pencil"></i> Edit
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($is_author || $is_high_council): ?>
                                        <a href="remark-delete.php?log_id=<?= $log['log_id'] ?>"
                                           class="btn-delete-remark"
                                           onclick="return confirm('Delete this remark? This cannot be undone.');">
                                            <i class="bi bi-trash"></i> Delete
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

            </div>

        </div><!-- /page-wrap -->
    </div><!-- /content -->

    <?php include('dashboard/dist/footer.php'); ?>
</div><!-- /main-wrap -->

</body>
</html>