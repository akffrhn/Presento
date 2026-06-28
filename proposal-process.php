<?php
session_start();
include('dbcon.php');
include('notification-helper.php');

/* ============================================================
   AUTH CHECKS — must be logged in and have role 'CYCOM'
============================================================ */
if (empty($_SESSION['user_id'])) {
    die("<script>
            alert('Please log in');
            window.location.href='dashboard/dist/index.php';
         </script>");
}

$session_role      = $_SESSION['role'] ?? '';
$session_club_role = $_SESSION['clubrole'] ?? '';
$current_user_id   = (int) $_SESSION['user_id'];

if ($session_role !== 'CYCOM') {
    die("<script>
            alert('You are not authorized to perform this action');
            window.location.href='dashboard/dist/index.php';
         </script>");
}

$is_high_council = ($session_club_role === 'High Council');

$action      = $_GET['action'] ?? $_POST['action'] ?? '';
$proposal_id = (int) ($_GET['proposal_id'] ?? $_POST['proposal_id'] ?? 0);
$ref_user_id = (int) ($_GET['user_id'] ?? $_POST['user_id'] ?? $current_user_id);

if ($proposal_id <= 0) {
    die("<script>
            alert('Missing proposal_id');
            window.location.href='proposal-list.php?user_id=" . $ref_user_id . "';
         </script>");
}

/* ============================================================
   Helper: fetch proposal
   NOTE: we select * here (rather than a fixed column list) so
   that whatever extra columns your `proposal` table has
   (description, content, category, date_submitted, file, etc.)
   are automatically available to the comment view below.
   Adjust the SELECT back to a fixed list if you'd rather be
   explicit about columns.
============================================================ */
function get_proposal(mysqli $condb, int $proposal_id): ?array {
    $q = "SELECT * FROM proposal WHERE proposal_id = ?";
    $s = $condb->prepare($q);
    $s->bind_param("i", $proposal_id);
    $s->execute();
    return $s->get_result()->fetch_assoc();
}

/* ============================================================
   ACTION: accept / reject — High Council only
   Updates proposal.status, logs to proposal_status_log,
   and notifies the proposal owner.
============================================================ */
if ($action === 'accept' || $action === 'reject') {

    if (!$is_high_council) {
        die("<script>
                alert('Only High Council members can accept or reject proposals');
                window.location.href='proposal-list.php?user_id=" . $ref_user_id . "';
             </script>");
    }

    $proposal = get_proposal($condb, $proposal_id);
    if (!$proposal) {
        die("<script>
                alert('Proposal not found');
                window.location.href='proposal-list.php?user_id=" . $ref_user_id . "';
             </script>");
    }

    $new_status = ($action === 'accept') ? 'Accepted' : 'Rejected';
    $remarks    = trim($_GET['remarks'] ?? $_POST['remarks'] ?? '');

    // 1. Update the proposal's current status
    $upd = $condb->prepare("UPDATE proposal SET status = ? WHERE proposal_id = ?");
    $upd->bind_param("si", $new_status, $proposal_id);
    $upd->execute();
    $upd->close();

    // 2. Log the decision
    $log = $condb->prepare(
        "INSERT INTO proposal_status_log (proposal_id, reviewed_by, status, remarks, changed_at)
         VALUES (?, ?, ?, ?, NOW())"
    );
    $log->bind_param("iiss", $proposal_id, $current_user_id, $new_status, $remarks);
    $log->execute();
    $log->close();

    // 3. Notify the proposal owner
    $message = "Your proposal \"" . $proposal['title'] . "\" has been " . strtolower($new_status) . ".";
    send_notification($condb, (int) $proposal['user_id'], $proposal_id, $message);

    $flash = ($action === 'accept') ? 'accepted' : 'rejected';

    header("Location: proposal-list.php?user_id=" . $ref_user_id . "&flash=" . $flash);
    exit;
}

/* ============================================================
   ACTION: comment_form — show proposal details + comment form (GET)
============================================================ */
if ($action === 'comment_form') {

    $proposal = get_proposal($condb, $proposal_id);

    if (!$proposal) {
        die("<script>
                alert('Proposal not found');
                window.location.href='proposal-list.php?user_id=" . $ref_user_id . "';
             </script>");
    }

    // Fields straight from the `proposal` table schema
    $objectives      = $proposal['objectives'] ?? '';
    $activities      = $proposal['activities'] ?? '';
    $submitted_date  = !empty($proposal['date_submitted'])
        ? date('d M Y, h:i A', strtotime($proposal['date_submitted']))
        : '';
    $status          = $proposal['status'] ?? '';
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Add Comment — CYCOM E-Proposal</title>

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

            .form-row textarea {
                width: 100%;
                min-height: 120px;
                padding: 8px 10px;
                border-radius: 4px;
                border: 1px solid #ccc;
                font-family: Arial, sans-serif;
                font-size: 0.95rem;
                resize: vertical;
                box-sizing: border-box;
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

            /* Proposal detail view */
            .proposal-view {
                background: #3c0e26;
                border: 1px solid rgba(255,255,255,0.3);
                border-radius: 6px;
                padding: 1rem 1.25rem;
                margin-bottom: 1.25rem;
                max-height: 320px;
                overflow-y: auto;
            }
            .proposal-view .meta-row {
                display: flex;
                flex-wrap: wrap;
                gap: 0.75rem 1.5rem;
                margin-bottom: 0.75rem;
                font-size: 0.85rem;
                color: #e6c7d8;
            }
            .proposal-view .meta-row span strong {
                color: #fff;
                font-weight: 600;
            }
            .status-badge {
                display: inline-block;
                padding: 2px 10px;
                border-radius: 999px;
                font-size: 0.75rem;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.03em;
                background: rgba(255,255,255,0.15);
                border: 1px solid rgba(255,255,255,0.4);
            }
            .proposal-view .description-label {
                font-size: 0.8rem;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                color: #e6c7d8;
                margin-bottom: 0.25rem;
            }
            .proposal-view .description-text {
                white-space: pre-wrap;
                line-height: 1.5;
                font-size: 0.95rem;
            }
            .description-text.empty {
                color: #c9a3bb;
                font-style: italic;
            }
        </style>
    </head>

    <body>

    <?php include('dashboard/dist/navigation1.php'); ?>

    <div class="main-wrap">
        <div class="content">

            <div class="form-wrap">

                <h3>Comment on: <?= htmlspecialchars($proposal['title']) ?></h3>

                <!-- Proposal detail view, so the reviewer knows what they're commenting on -->
                <div class="proposal-view">
                    <div class="meta-row">
                        <?php if ($status !== ''): ?>
                            <span><strong>Status:</strong> <span class="status-badge"><?= htmlspecialchars($status) ?></span></span>
                        <?php endif; ?>
                        <?php if ($submitted_date !== ''): ?>
                            <span><strong>Submitted:</strong> <?= htmlspecialchars($submitted_date) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="description-label">Objectives</div>
                    <?php if ($objectives !== ''): ?>
                        <div class="description-text"><?= nl2br(htmlspecialchars($objectives)) ?></div>
                    <?php else: ?>
                        <div class="description-text empty">No objectives provided.</div>
                    <?php endif; ?>

                    <div class="description-label" style="margin-top: 0.9rem;">Activities</div>
                    <?php if ($activities !== ''): ?>
                        <div class="description-text"><?= nl2br(htmlspecialchars($activities)) ?></div>
                    <?php else: ?>
                        <div class="description-text empty">No activities provided.</div>
                    <?php endif; ?>
                </div>

                <form method="POST" action="proposal-process.php" class="user-form" id="commentForm">
                    <input type="hidden" name="action" value="comment_submit">
                    <input type="hidden" name="proposal_id" value="<?= htmlspecialchars($proposal_id) ?>">
                    <input type="hidden" name="user_id" value="<?= htmlspecialchars($ref_user_id) ?>">

                    <div class="form-row">
                        <label for="comment_text">Comment</label>
                        <textarea
                            id="comment_text"
                            name="comment_text"
                            placeholder="Write your comment..."
                            title="Comment cannot be empty"
                            required></textarea>
                    </div>

                    <div class="btn-row">
                        <a class="btn-cancel" href="proposal-list.php?user_id=<?= htmlspecialchars($ref_user_id) ?>">Cancel</a>
                        <button type="submit" class="btn-submit">Submit</button>
                    </div>
                </form>

            </div><!-- /form-wrap -->
        </div><!-- /content -->

        <?php include('dashboard/dist/footer.php'); ?>

    </div><!-- /main-wrap -->

    </body>
    </html>
    <?php
    exit;
}

/* ============================================================
   ACTION: comment_submit — save the comment (POST)
   Comments are stored in proposal_status_log with the
   proposal's CURRENT status unchanged (status doesn't move,
   only a remark is logged), and the owner is notified.
============================================================ */
if ($action === 'comment_submit') {

    $comment_text = trim($_POST['comment_text'] ?? '');

    if ($comment_text === '') {
        die("<script>
                alert('Comment cannot be empty');
                window.location.href='proposal-process.php?action=comment_form&proposal_id=" . $proposal_id . "&user_id=" . $ref_user_id . "';
             </script>");
    }

    $proposal = get_proposal($condb, $proposal_id);
    if (!$proposal) {
        die("<script>
                alert('Proposal not found');
                window.location.href='proposal-list.php?user_id=" . $ref_user_id . "';
             </script>");
    }

    // Log the comment, keeping the proposal's current status
    $log = $condb->prepare(
        "INSERT INTO proposal_status_log (proposal_id, reviewed_by, status, remarks, changed_at)
         VALUES (?, ?, ?, ?, NOW())"
    );
    $log->bind_param("iiss", $proposal_id, $current_user_id, $proposal['status'], $comment_text);
    $log->execute();
    $log->close();

    // Notify the proposal owner
    $message = "New comment on your proposal \"" . $proposal['title'] . "\".";
    send_notification($condb, (int) $proposal['user_id'], $proposal_id, $message);

    header("Location: proposal-list.php?user_id=" . $ref_user_id . "&flash=commented");
    exit;
}

/* ============================================================
   Unknown action
============================================================ */
die("<script>
        alert('Invalid action');
        window.location.href='proposal-list.php?user_id=" . $ref_user_id . "';
     </script>");