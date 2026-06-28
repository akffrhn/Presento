<?php
/**
 * Notification helper.
 *
 * Include this file (after dbcon.php, so $condb already exists) and call
 * send_notification() wherever something happens that the recipient should
 * be told about — e.g. when a proposal's status changes, or a remark is added.
 *
 * Example usage, e.g. right after a status update in your review-handling script:
 *
 *     include('notification-helper.php');
 *     send_notification(
 *         $condb,
 *         $proposal['user_id'],      // who should be notified (the submitter)
 *         $proposal_id,              // which proposal this relates to
 *         "Your proposal \"{$proposal['title']}\" was {$new_status}."
 *     );
 */

if (!function_exists('send_notification')) {
    function send_notification(mysqli $condb, int $user_id, int $proposal_id, string $message): bool {
        if ($user_id <= 0 || $proposal_id <= 0 || trim($message) === '') {
            return false;
        }

        $stmt = $condb->prepare("
            INSERT INTO notification (user_id, proposal_id, message, is_read, created_at)
            VALUES (?, ?, ?, 0, NOW())
        ");
        $stmt->bind_param("iis", $user_id, $proposal_id, $message);
        $ok = $stmt->execute();
        $stmt->close();

        return $ok;
    }
}
