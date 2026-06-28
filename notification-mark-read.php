<?php
session_start();
include('dbcon.php');

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$session_user_id = (int)$_SESSION['user_id'];
$action           = $_POST['action'] ?? '';

if ($action === 'mark_one') {
    $notif_id = (int)($_POST['notif_id'] ?? 0);
    if ($notif_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing notif_id']);
        exit;
    }

    // Scoped to the logged-in user so nobody can mark someone else's notification read
    $stmt = $condb->prepare("
        UPDATE notification
        SET is_read = 1
        WHERE notif_id = ? AND user_id = ?
    ");
    $stmt->bind_param("ii", $notif_id, $session_user_id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true]);

} elseif ($action === 'mark_all') {
    $stmt = $condb->prepare("
        UPDATE notification
        SET is_read = 1
        WHERE user_id = ? AND is_read = 0
    ");
    $stmt->bind_param("i", $session_user_id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true]);

} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
