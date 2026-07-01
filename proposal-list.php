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
   FETCH PROPOSALS
============================================================ */
$search = trim($_GET['search'] ?? '');
$is_cycom = ($session_role === 'CYCOM');

$session_club_role = $_SESSION['clubrole'] ?? '';
$is_high_council = ($session_club_role === 'High Council');

$flash = $_GET['flash'] ?? '';

/* ----------------------------------------------------------
   Sorting — whitelist allowed sort keys to keep ORDER BY safe.
   Default is 'status_asc' so proposals are grouped by status
   on first load. The 'Sort by' placeholder only appears after
   the user manually clears back to no selection (unlikely but
   handled gracefully).
---------------------------------------------------------- */
$sortOptions = [
    'date_desc'   => ['label' => 'Newest First',                  'sql' => 'p.date_submitted DESC'],
    'date_asc'    => ['label' => 'Oldest First',                   'sql' => 'p.date_submitted ASC'],
    'status_asc'  => ['label' => 'Status (Submitted → Rejected)',  'sql' => "FIELD(p.status, 'Submitted', 'Under Review', 'Resubmitted', 'Accepted', 'Rejected') ASC"],
    'status_desc' => ['label' => 'Status (Rejected → Submitted)',  'sql' => "FIELD(p.status, 'Submitted', 'Under Review', 'Resubmitted', 'Accepted', 'Rejected') DESC"],
];

$default_sort = 'status_asc';
$sort = $_GET['sort'] ?? $default_sort;
if (!array_key_exists($sort, $sortOptions)) {
    $sort = $default_sort;
}
$orderBySql = $sortOptions[$sort]['sql'];

if ($is_cycom) {
    if ($search !== '') {
        $pquery = "SELECT p.*, CONCAT(u.fname, ' ', u.lname) AS submitter_name
                   FROM proposal p
                   JOIN user u ON u.user_id = p.user_id
                   WHERE p.title LIKE ?
                   ORDER BY $orderBySql";
        $pstmt = $condb->prepare($pquery);
        $likeTerm = "%" . $search . "%";
        $pstmt->bind_param("s", $likeTerm);
    } else {
        $pquery = "SELECT p.*, CONCAT(u.fname, ' ', u.lname) AS submitter_name
                   FROM proposal p
                   JOIN user u ON u.user_id = p.user_id
                   ORDER BY $orderBySql";
        $pstmt = $condb->prepare($pquery);
    }
} else {
    if ($search !== '') {
        $pquery = "SELECT * FROM proposal p WHERE p.user_id = ? AND p.title LIKE ? ORDER BY $orderBySql";
        $pstmt = $condb->prepare($pquery);
        $likeTerm = "%" . $search . "%";
        $pstmt->bind_param("is", $current_user_id, $likeTerm);
    } else {
        $pquery = "SELECT * FROM proposal p WHERE p.user_id = ? ORDER BY $orderBySql";
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
        .proposal-wrap { padding: 1.5rem; }

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
            flex-wrap: wrap;
            align-items: center;
        }

        .search-form input[type="text"] {
            padding: 6px 10px;
            border-radius: 4px;
            border: 1px solid #ccc;
        }

        .search-form select {
            padding: 6px 10px;
            border-radius: 4px;
            border: 1px solid #ccc;
            background: #fff;
            color: #491231;
            font-weight: 600;
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

        .proposal-table th { font-weight: 700; }

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
        .status-resubmitted { background: #1a6b8a; }

        .proposal-table a { color: #fff; text-decoration: underline; }
        .proposal-table a:hover { opacity: 0.8; }

        .new-proposal-btn { display: inline-block; color: #fff; text-decoration: underline; }

        .flash-success { background: #28a745; color: #fff; padding: 10px 16px; border-radius: 4px; margin-bottom: 1rem; }
        .flash-warning { background: #b8860b; color: #fff; padding: 10px 16px; border-radius: 4px; margin-bottom: 1rem; }
        .flash-danger  { background: #C89DB8; color: #491231; padding: 10px 16px; border-radius: 4px; margin-bottom: 1rem; }
    </style>
</head>

<body>

<?php include('dashboard/dist/navigation1.php'); ?>

<div class="main-wrap">
    <div class="content">

        <div class="proposal-wrap">

            <h3>Proposal List</h3>

            <?php if ($flash === 'accepted'): ?>
                <div class="flash-success">Proposal accepted.</div>
            <?php elseif ($flash === 'rejected'): ?>
                <div class="flash-danger">Proposal rejected.</div>
            <?php elseif ($flash === 'commented'): ?>
                <div class="flash-warning">Comment submitted.</div>
            <?php elseif ($flash === 'proposal_updated'): ?>
                <div class="flash-success">Proposal updated successfully.</div>
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

                    <select name="sort" onchange="this.form.submit()">
                        <option value="" disabled <?= !array_key_exists($sort, $sortOptions) ? 'selected' : '' ?>>Sort by</option>
                        <?php foreach ($sortOptions as $key => $opt): ?>
                            <option value="<?= htmlspecialchars($key) ?>" <?= $sort === $key ? 'selected' : '' ?>>
                                <?= htmlspecialchars($opt['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <button type="submit">Search</button>
                    <?php if ($search !== '' || $sort !== $default_sort): ?>
                        <a href="?user_id=<?= htmlspecialchars($current_user_id) ?>" class="clear-link">Clear</a>
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
                        <th style="width:200px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($proposals->num_rows > 0): ?>
                        <?php $no = 1; while ($row = $proposals->fetch_assoc()): ?>
                            <?php
                                $is_owner = ((int) $row['user_id'] === $session_user_id);
                                $is_editable = $is_cycom
                                    || in_array($row['status'], ['Submitted', 'Rejected'], true);
                            ?>
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
                                            'Resubmitted'  => 'status-resubmitted',
                                            default        => 'status-submitted',
                                        };
                                    ?>
                                    <span class="status-badge <?= $statusClass ?>">
                                        <?= htmlspecialchars($status) ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="proposal-view.php?id=<?= $row['proposal_id'] ?>">View</a>

                                    <?php if ($is_owner && $is_editable): ?>
                                        |
                                        <a href="proposal-update.php?proposal_id=<?= htmlspecialchars($row['proposal_id']) ?>">Update</a>
                                    <?php elseif ($is_owner && !$is_editable): ?>
                                        | <span style="opacity:.5; cursor:not-allowed;" title="Cannot edit a proposal that is <?= htmlspecialchars($status) ?>">Update</span>
                                    <?php endif; ?>

                                    <?php if ($is_cycom && !$is_owner): ?>
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
                                    <?php elseif ($is_cycom && $is_owner): ?>
                                        |
                                        <a href="proposal-process.php?action=comment_form&proposal_id=<?= htmlspecialchars($row['proposal_id']) ?>&user_id=<?= htmlspecialchars($current_user_id) ?>">Comment</a>
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