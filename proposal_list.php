<?php
session_start();
include('dbcon.php');

if (empty($_GET['user_id'])) {
    die("<script>
            alert('Missing user_id');
            window.location.href='dashboard/dist/index.php';
         </script>");
}

$current_user_id = $_GET['user_id'];

$query = "SELECT * FROM USER WHERE user_id = ?";
$stmt = $condb->prepare($query);
$stmt->bind_param("s", $current_user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows != 1) {
    die("<script>
            alert('User not found');
            window.location.href='dashboard/dist/index.php';
         </script>");
}

$user = $result->fetch_assoc();
$user_picture = $user['profilepicture'] ?? '';

/* ============================================================
   FETCH PROPOSALS — all proposals belonging to this user
============================================================ */
$pquery = "SELECT * FROM proposal WHERE user_id = ? ORDER BY date_submitted DESC";
$pstmt = $condb->prepare($pquery);
$pstmt->bind_param("i", $current_user_id);
$pstmt->execute();
$proposals = $pstmt->get_result();
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Proposals — CYCOM E-Proposal</title>

    <link href="/Presento/assets/img/favicon.png" rel="icon">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/Presento/dashboard/dist/assets/css/mainc1.css">
    <link rel="stylesheet" href="/Presento/assets/css/style.css">

    <style>
        body {
            background: #491231;
        }

        .proposal-wrap {
            padding: 1.5rem;
        }

        .proposal-wrap h3 {
            color: #fff;
            font-weight: 700;
            margin-bottom: 1.25rem;
        }

        .proposal-table {
            width: 100%;
            border-collapse: collapse;
        }

        .proposal-table th,
        .proposal-table td {
            border: 1px solid #fff;
            padding: 10px 16px;
            color: #fff;
            text-align: left;
        }

        .proposal-table th {
            font-weight: 700;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 14px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .status-submitted   { background: #6c757d; }
        .status-underreview { background: #b8860b; }
        .status-accepted    { background: #28a745; }
        .status-rejected    { background: #C89DB8; color: #491231; }

        .proposal-table a {
            color: #fff;
            text-decoration: underline;
        }

        .proposal-table a:hover {
            opacity: 0.8;
        }

        .new-proposal-btn {
            display: inline-block;
            margin-bottom: 1rem;
            color: #fff;
            text-decoration: underline;
        }
    </style>
</head>

<body>

<?php include('dashboard/dist/navigation1.php'); ?>

<div class="main-wrap">
    <div class="content">

        <div class="proposal-wrap">

            <h3>Proposal List</h3>

            <div style="text-align:right;">
                <a class="new-proposal-btn" href="proposal_submission.php?user_id=<?= htmlspecialchars($user['user_id']) ?>">
                    + New Proposal
                </a>
            </div>

            <table class="proposal-table">
                <thead>
                    <tr>
                        <th style="width:60px;">No</th>
                        <th>Project Name</th>
                        <th style="width:160px;">Status</th>
                        <th style="width:160px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($proposals->num_rows > 0): ?>
                        <?php $no = 1; while ($row = $proposals->fetch_assoc()): ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><?= htmlspecialchars($row['title']) ?></td>
                                <td>
                                    <?php
                                        $status = $row['status'];
                                        $statusClass = match($status) {
                                            'Accepted'     => 'status-accepted',
                                            'Rejected'     => 'status-rejected',
                                            'Under Review' => 'status-underreview',
                                            default        => 'status-submitted',
                                        };
                                    ?>
                                    <span class="status-badge <?= $statusClass ?>">
                                        <?= htmlspecialchars($status) ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="proposalview.php?proposal_id=<?= htmlspecialchars($row['proposal_id']) ?>">View</a>
                                    |
                                    <a href="proposalupdate.php?proposal_id=<?= htmlspecialchars($row['proposal_id']) ?>">Update</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align:center;">No proposals submitted yet.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

        </div><!-- /proposal-wrap -->
    </div><!-- /content -->

    <?php include('dashboard/dist/footer.php'); ?>

</div><!-- /main-wrap -->

</body>
</html>