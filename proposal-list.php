<?php
session_start();
include('dbcon.php');

/* ============================================================
   AUTH CHECKS
============================================================ */
if (empty($_SESSION['user_id'])) {
    die("<script>
            alert('Please log in');
            window.location.href='dashboard/dist/index.php';
         </script>");
}

if (empty($_GET['user_id'])) {
    die("<script>
            alert('Missing user_id');
            window.location.href='dashboard/dist/index.php';
         </script>");
}

$current_user_id = (int) $_GET['user_id'];
$session_user_id = (int) $_SESSION['user_id'];
$session_role    = $_SESSION['role'] ?? '';

// Only the member themself or a 'CYCOM' role can view this page
if ($current_user_id !== $session_user_id && $session_role !== 'CYCOM') {
    die("<script>
            alert('You are not authorized to view this page');
            window.location.href='dashboard/dist/index.php';
         </script>");
}

/* ============================================================
   FETCH USER
============================================================ */
$query = "SELECT * FROM user WHERE user_id = ?";
$stmt = $condb->prepare($query);
$stmt->bind_param("i", $current_user_id);
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
   FETCH PROPOSALS — with optional search by title
   - cycom role: sees ALL proposals (any user), with submitter name
   - regular member: sees only their own proposals
============================================================ */
$search = trim($_GET['search'] ?? '');
$is_cycom = ($session_role === 'CYCOM');

// Only members of the High Council club role may Accept/Reject proposals
$session_club_role = $_SESSION['clubrole'] ?? '';
$is_high_council = ($session_club_role === 'High Council');

// Flash message after accept/reject/comment actions
$flash = $_GET['flash'] ?? '';

if ($is_cycom) {
    if ($search !== '') {
        $pquery = "SELECT p.*, CONCAT(u.fname, ' ', u.lname) AS submitter_name
                   FROM proposal p
                   JOIN user u ON u.user_id = p.user_id
                   WHERE p.title LIKE ?
                   ORDER BY p.date_submitted DESC";
        $pstmt = $condb->prepare($pquery);
        $likeTerm = "%" . $search . "%";
        $pstmt->bind_param("s", $likeTerm);
    } else {
        $pquery = "SELECT p.*, CONCAT(u.fname, ' ', u.lname) AS submitter_name
                   FROM proposal p
                   JOIN user u ON u.user_id = p.user_id
                   ORDER BY p.date_submitted DESC";
        $pstmt = $condb->prepare($pquery);
    }
} else {
    if ($search !== '') {
        $pquery = "SELECT * FROM proposal WHERE user_id = ? AND title LIKE ? ORDER BY date_submitted DESC";
        $pstmt = $condb->prepare($pquery);
        $likeTerm = "%" . $search . "%";
        $pstmt->bind_param("is", $current_user_id, $likeTerm);
    } else {
        $pquery = "SELECT * FROM proposal WHERE user_id = ? ORDER BY date_submitted DESC";
        $pstmt = $condb->prepare($pquery);
        $pstmt->bind_param("i", $current_user_id);
    }
}

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

        .proposal-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 10px;
        }

        .search-form {
            display: flex;
            gap: 8px;
        }

        .search-form input[type="text"] {
            padding: 6px 10px;
            border-radius: 4px;
            border: 1px solid #ccc;
        }

        .search-form button {
            padding: 6px 14px;
            border-radius: 4px;
            border: none;
            background: #fff;
            color: #491231;
            font-weight: 600;
            cursor: pointer;
        }

        .search-form .clear-link {
            color: #fff;
            align-self: center;
            text-decoration: underline;
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

            <?php if ($flash === 'accepted'): ?>
                <div style="background:#28a745; color:#fff; padding:10px 16px; border-radius:4px; margin-bottom:1rem;">
                    Proposal accepted.
                </div>
            <?php elseif ($flash === 'rejected'): ?>
                <div style="background:#C89DB8; color:#491231; padding:10px 16px; border-radius:4px; margin-bottom:1rem;">
                    Proposal rejected.
                </div>
            <?php elseif ($flash === 'commented'): ?>
                <div style="background:#b8860b; color:#fff; padding:10px 16px; border-radius:4px; margin-bottom:1rem;">
                    Comment submitted.
                </div>
            <?php endif; ?>

            <div class="proposal-toolbar">

                <form method="GET" action="" class="search-form">
                    <input type="hidden" name="user_id" value="<?= htmlspecialchars($current_user_id) ?>">
                    <input
                        type="text"
                        name="search"
                        value="<?= htmlspecialchars($search) ?>"
                        placeholder="Search by project name..."
                    >
                    <button type="submit">Search</button>
                    <?php if ($search !== ''): ?>
                        <a href="?user_id=<?= htmlspecialchars($current_user_id) ?>" class="clear-link">
                            Clear
                        </a>
                    <?php endif; ?>
                </form>

                <a class="new-proposal-btn" href="proposal-submission.php?user_id=<?= htmlspecialchars($user['user_id']) ?>">
                    + New Proposal
                </a>
            </div>

            <table class="proposal-table">
                <thead>
                    <tr>
                        <th style="width:60px;">No</th>
                        <th>Project Name</th>
                        <?php if ($is_cycom): ?>
                            <th style="width:180px;">Submitted By</th>
                        <?php endif; ?>
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
                                <?php if ($is_cycom): ?>
                                    <td><?= htmlspecialchars($row['submitter_name'] ?? 'Unknown') ?></td>
                                <?php endif; ?>
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
                                    <?php if ($is_cycom): ?>
                                        |
                                        <a href="proposal-process.php?action=comment_form&proposal_id=<?= htmlspecialchars($row['proposal_id']) ?>&user_id=<?= htmlspecialchars($current_user_id) ?>">Comment</a>
                                        <?php if ($is_high_council): ?>
                                            |
                                            <a href="proposal-process.php?action=accept&proposal_id=<?= htmlspecialchars($row['proposal_id']) ?>&user_id=<?= htmlspecialchars($current_user_id) ?>"
                                               onclick="return confirm('Accept this proposal?');">Accept</a>
                                            |
                                            <a href="proposal-process.php?action=reject&proposal_id=<?= htmlspecialchars($row['proposal_id']) ?>&user_id=<?= htmlspecialchars($current_user_id) ?>"
                                               onclick="return confirm('Reject this proposal?');">Reject</a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        |
                                        <a href="proposalupdate.php?proposal_id=<?= htmlspecialchars($row['proposal_id']) ?>">Update</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?= $is_cycom ? 5 : 4 ?>" style="text-align:center;">
                                <?= $search !== ''
                                    ? 'No proposals found matching "' . htmlspecialchars($search) . '".'
                                    : 'No proposals submitted yet.' ?>
                            </td>
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