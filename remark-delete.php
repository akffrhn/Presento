<?php
session_start();
include('dbcon.php');

/* ============================================================
   AUTH CHECKS — High Council only can delete
============================================================ */
if (empty($_SESSION['user_id'])) {
    die("<script>alert('Please log in'); window.location.href='dashboard/dist/index.php';</script>");
}

$session_role     = $_SESSION['role'] ?? '';
$session_clubrole = $_SESSION['clubrole'] ?? '';

if ($session_role !== 'CYCOM' || $session_clubrole !== 'High Council') {
    die("<script>alert('Only High Council members can delete remarks.'); window.location.href='dashboard/dist/index.php';</script>");
}

$log_id = isset($_GET['log_id']) ? (int)$_GET['log_id'] : 0;
if ($log_id <= 0) {
    die("<script>alert('Invalid remark.'); window.location.href='proposal-list.php';</script>");
}

/* ============================================================
   FETCH LOG TO GET proposal_id FOR REDIRECT
============================================================ */
$stmt = $condb->prepare("SELECT proposal_id FROM proposal_status_log WHERE log_id = ?");
$stmt->bind_param("i", $log_id);
$stmt->execute();
$log = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$log) {
    die("<script>alert('Remark not found.'); window.location.href='proposal-list.php';</script>");
}

$proposal_id = $log['proposal_id'];

/* ============================================================
   DELETE
============================================================ */
$del = $condb->prepare("DELETE FROM proposal_status_log WHERE log_id = ?");
$del->bind_param("i", $log_id);

if ($del->execute()) {
    $del->close();

    // Sync proposal status to the latest log entry, or back to Submitted if no logs remain
    $latest = $condb->prepare("
        SELECT status FROM proposal_status_log
        WHERE proposal_id = ?
        ORDER BY changed_at DESC
        LIMIT 1
    ");
    $latest->bind_param("i", $proposal_id);
    $latest->execute();
    $latestLog = $latest->get_result()->fetch_assoc();
    $latest->close();

    $newStatus = $latestLog ? $latestLog['status'] : 'Submitted';

    $sync = $condb->prepare("UPDATE proposal SET status = ? WHERE proposal_id = ?");
    $sync->bind_param("si", $newStatus, $proposal_id);
    $sync->execute();
    $sync->close();

    header("Location: proposal-view.php?id={$proposal_id}&flash=remark_deleted");
    exit;
} else {
    header("Location: proposal-view.php?id={$proposal_id}&flash=remark_delete_failed");
    exit;
}
?>
