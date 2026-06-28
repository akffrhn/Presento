<?php
session_start();
include('dbcon.php');

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
            window.location.href='myproposals.php?user_id=" . $ref_user_id . "';
         </script>");
}

/* ============================================================
   Helper: fetch proposal (need owner's user_id + title for notifications)
============================================================ */
function get_proposal(mysqli $condb, int $proposal_id): ?array {
    $q = "SELECT proposal_id, user_id, title, status FROM proposal WHERE proposal_id = ?";
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
                window.location.href='myproposals.php?user_id=" . $ref_user_id . "';
             </script>");
    }

    $proposal = get_proposal($condb, $proposal_id);
    if (!$proposal) {
        die("<script>
                alert('Proposal not found');
                window.location.href='myproposals.php?user_id=" . $ref_user_id . "';
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
    $notif = $condb->prepare(
        "INSERT INTO notification (user_id, proposal_id, message, is_read, created_at)
         VALUES (?, ?, ?, 0, NOW())"
    );
    $notif->bind_param("iis", $proposal['user_id'], $proposal_id, $message);
    $notif->execute();
    $notif->close();

    $flash = ($action === 'accept') ? 'accepted' : 'rejected';

    header("Location: myproposals.php?user_id=" . $ref_user_id . "&flash=" . $flash);
    exit;
}

/* ============================================================
   ACTION: comment_form — show a small comment form (GET)
============================================================ */
if ($action === 'comment_form') {

    $proposal = get_proposal($condb, $proposal_id);

    if (!$proposal) {
        die("<script>
                alert('Proposal not found');
                window.location.href='myproposals.php?user_id=" . $ref_user_id . "';
             </script>");
    }
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <title>Add Comment</title>
        <style>
            body {
                background: #491231;
                font-family: Arial, sans-serif;
                color: #fff;
                display: flex;
                justify-content: center;
                align-items: center;
                height: 100vh;
                margin: 0;
            }
            .comment-box {
                background: #5a1640;
                padding: 2rem;
                border-radius: 8px;
                width: 400px;
                border: 2px solid #fff;
            }
            .comment-box h3 {
                margin-top: 0;
            }
            textarea {
                width: 100%;
                min-height: 120px;
                padding: 8px;
                border-radius: 4px;
                border: 1px solid #ccc;
                font-family: inherit;
                resize: vertical;
                box-sizing: border-box;
            }
            .btn-row {
                margin-top: 1rem;
                display: flex;
                justify-content: flex-end;
                gap: 10px;
            }
            button, .btn-cancel {
                padding: 8px 16px;
                border-radius: 4px;
                border: none;
                cursor: pointer;
                font-weight: 600;
                text-decoration: none;
            }
            button {
                background: #fff;
                color: #491231;
            }
            .btn-cancel {
                background: transparent;
                color: #fff;
                border: 1px solid #fff;
            }
        </style>
    </head>
    <body>
        <div class="comment-box">
            <h3>Comment on: <?= htmlspecialchars($proposal['title']) ?></h3>
            <form method="POST" action="proposal_process.php">
                <input type="hidden" name="action" value="comment_submit">
                <input type="hidden" name="proposal_id" value="<?= htmlspecialchars($proposal_id) ?>">
                <input type="hidden" name="user_id" value="<?= htmlspecialchars($ref_user_id) ?>">
                <textarea name="comment_text" placeholder="Write your comment..." required></textarea>
                <div class="btn-row">
                    <a class="btn-cancel" href="myproposals.php?user_id=<?= htmlspecialchars($ref_user_id) ?>">Cancel</a>
                    <button type="submit">Submit</button>
                </div>
            </form>
        </div>
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
                window.location.href='proposal_process.php?action=comment_form&proposal_id=" . $proposal_id . "&user_id=" . $ref_user_id . "';
             </script>");
    }

    $proposal = get_proposal($condb, $proposal_id);
    if (!$proposal) {
        die("<script>
                alert('Proposal not found');
                window.location.href='myproposals.php?user_id=" . $ref_user_id . "';
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
    $notif = $condb->prepare(
        "INSERT INTO notification (user_id, proposal_id, message, is_read, created_at)
         VALUES (?, ?, ?, 0, NOW())"
    );
    $notif->bind_param("iis", $proposal['user_id'], $proposal_id, $message);
    $notif->execute();
    $notif->close();

    header("Location: myproposals.php?user_id=" . $ref_user_id . "&flash=commented");
    exit;
}

/* ============================================================
   Unknown action
============================================================ */
die("<script>
        alert('Invalid action');
        window.location.href='myproposals.php?user_id=" . $ref_user_id . "';
     </script>");