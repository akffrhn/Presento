<?php
session_start();
include('dbcon.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid request method");
}

$user_id    = $_POST['user_id'] ?? '';
$title      = trim($_POST['title'] ?? '');
$objectives = trim($_POST['objectives'] ?? '');
$activities = trim($_POST['activities'] ?? '');

if (empty($user_id) || empty($title) || empty($objectives) || empty($activities)) {
    die("<script>
            alert('All fields are required');
            window.history.back();
         </script>");
}

$query = "INSERT INTO proposal (user_id, title, objectives, activities, status, date_submitted)
          VALUES (?, ?, ?, ?, 'Submitted', NOW())";
$stmt = $condb->prepare($query);
$stmt->bind_param("isss", $user_id, $title, $objectives, $activities);

if ($stmt->execute()) {
    echo "<script>
            alert('Proposal submitted successfully');
            window.location.href='dashboard/dist/index.php';
          </script>";
} else {
    echo "<script>
            alert('Failed to submit proposal');
            window.history.back();
          </script>";
}