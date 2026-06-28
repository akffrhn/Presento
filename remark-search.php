<?php
session_start();
include('dbcon.php');

/* ============================================================
   AUTH CHECKS — CYCOM only
============================================================ */
if (empty($_SESSION['user_id'])) {
    die("<script>alert('Please log in'); window.location.href='dashboard/dist/index.php';</script>");
}

$session_role     = $_SESSION['role'] ?? '';
$session_clubrole = $_SESSION['clubrole'] ?? '';
$session_user_id  = (int)$_SESSION['user_id'];

if ($session_role !== 'CYCOM') {
    die("<script>alert('You are not authorized.'); window.location.href='dashboard/dist/index.php';</script>");
}

$is_high_council = ($session_clubrole === 'High Council');

/* ============================================================
   SEARCH FILTERS
============================================================ */
$search_keyword     = trim($_GET['keyword'] ?? '');
$filter_status      = $_GET['status'] ?? '';
$filter_date        = $_GET['date'] ?? '';
$filter_reviewer_id = isset($_GET['reviewer_id']) ? (int)$_GET['reviewer_id'] : 0;
$filter_proposal_id = isset($_GET['proposal_id']) ? (int)$_GET['proposal_id'] : 0;

$conditions = ["1=1"];
$params     = [];
$types      = "";

if ($filter_proposal_id > 0) {
    $conditions[] = "psl.proposal_id = ?";
    $params[]     = $filter_proposal_id;
    $types       .= "i";
}

if ($search_keyword !== '') {
    $conditions[] = "psl.remarks LIKE ?";
    $params[]     = "%" . $search_keyword . "%";
    $types       .= "s";
}

if ($filter_reviewer_id > 0) {
    $conditions[] = "psl.reviewed_by = ?";
    $params[]     = $filter_reviewer_id;
    $types       .= "i";
}

if (in_array($filter_status, ['Submitted', 'Under Review', 'Accepted', 'Rejected'], true)) {
    $conditions[] = "psl.status = ?";
    $params[]     = $filter_status;
    $types       .= "s";
}

if ($filter_date !== '') {
    $conditions[] = "DATE(psl.changed_at) = ?";
    $params[]     = $filter_date;
    $types       .= "s";
}

$where = implode(" AND ", $conditions);

$sql = "
    SELECT psl.*,
           p.title AS proposal_title,
           CONCAT(reviewer.fname, ' ', reviewer.lname) AS reviewer_name,
           reviewer.clubrole AS reviewer_clubrole,
           CONCAT(submitter.fname, ' ', submitter.lname) AS submitter_name
    FROM proposal_status_log psl
    JOIN proposal p        ON p.proposal_id   = psl.proposal_id
    JOIN user reviewer     ON reviewer.user_id = psl.reviewed_by
    JOIN user submitter    ON submitter.user_id = p.user_id
    WHERE {$where}
    ORDER BY psl.changed_at DESC
";

$stmt = $condb->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$results = $stmt->get_result();
$stmt->close();

/* ============================================================
   FETCH DISTINCT REVIEWERS (for the "From" dropdown)
============================================================ */
$reviewerListSql = "
    SELECT DISTINCT u.user_id, u.fname, u.lname, u.clubrole
    FROM proposal_status_log psl
    JOIN user u ON u.user_id = psl.reviewed_by
    ORDER BY u.fname, u.lname
";
$reviewerListResult = $condb->query($reviewerListSql);
$reviewerList = $reviewerListResult ? $reviewerListResult->fetch_all(MYSQLI_ASSOC) : [];

function statusBadge(string $status): string {
    $map = [
        'Submitted'    => '#6c757d',
        'Under Review' => '#b8860b',
        'Accepted'     => '#28a745',
        'Rejected'     => '#dc3545',
    ];
    $color = $map[$status] ?? '#999';
    return "<span style='background:{$color};color:#fff;padding:3px 10px;border-radius:20px;font-size:0.8rem;font-weight:600;'>{$status}</span>";
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Search Comments — CYCOM E-Proposal</title>
    <link href="/Presento/assets/img/favicon.png" rel="icon">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/Presento/dashboard/dist/assets/css/mainc1.css">
    <link rel="stylesheet" href="/Presento/assets/css/style.css">
    <style>
        body { background: #491231; }

        .page-wrap { padding: 1.5rem; }
        .page-wrap h3 { color: #fff; font-weight: 700; margin-bottom: 1.25rem; }

        /* Search bar */
        .search-card {
            background: #5a1640;
            border: 2px solid #fff;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: flex-end;
        }
        .filter-group { display: flex; flex-direction: column; gap: 5px; }
        .filter-group label { color: #fff; font-size: 0.85rem; font-weight: 600; }
        .filter-group input,
        .filter-group select {
            padding: 7px 10px;
            border-radius: 4px;
            border: 1px solid #ccc;
            font-size: 0.9rem;
            min-width: 160px;
        }
        .btn-search {
            padding: 8px 20px;
            background: #fff;
            color: #491231;
            border: none;
            border-radius: 4px;
            font-weight: 700;
            cursor: pointer;
        }
        .btn-clear {
            padding: 8px 16px;
            background: transparent;
            color: #fff;
            border: 1px solid #fff;
            border-radius: 4px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
        }

        /* Results */
        .results-count {
            color: #d8c4d0;
            font-size: 0.88rem;
            margin-bottom: 0.8rem;
        }

        .remark-card {
            background: #5a1640;
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 8px;
            padding: 1.2rem 1.5rem;
            margin-bottom: 1rem;
            color: #fff;
        }
        .remark-card-header {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 10px;
            align-items: center;
        }
        .proposal-title-link {
            font-weight: 700;
            font-size: 1rem;
            color: #fff;
            text-decoration: underline;
        }
        .proposal-title-link:hover { opacity: 0.8; color: #fff; }
        .remark-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            font-size: 0.82rem;
            color: #d8c4d0;
            margin-bottom: 10px;
        }
        .remark-text {
            background: #491231;
            border-radius: 5px;
            padding: 10px 14px;
            font-size: 0.92rem;
            line-height: 1.6;
            color: #f3e8ef;
        }
        .remark-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        .btn-edit, .btn-delete {
            padding: 5px 14px;
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
            border: none;
            cursor: pointer;
        }
        .btn-edit   { background: #fff; color: #491231; }
        .btn-delete { background: #dc3545; color: #fff; }

        .no-results {
            color: #d8c4d0;
            text-align: center;
            padding: 2rem;
            font-size: 0.95rem;
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #fff;
            background: transparent;
            border: 1px solid #fff;
            border-radius: 4px;
            padding: 7px 16px;
            font-size: 0.9rem;
            font-weight: 600;
            text-decoration: none;
            margin-bottom: 1.2rem;
        }
    </style>
</head>
<body>

<?php include('dashboard/dist/navigation1.php'); ?>

<div class="main-wrap">
    <div class="content">
        <div class="page-wrap">

            <a href="proposal-list.php?user_id=<?= $session_user_id ?>" class="btn-back">
                <i class="bi bi-arrow-left"></i> Back to Proposals
            </a>

            <h3><i class="bi bi-search"></i> Search Comments</h3>

            <!-- ── Filter Form ── -->
            <div class="search-card">
                <form method="GET" action="">
                    <?php if ($filter_proposal_id > 0): ?>
                        <input type="hidden" name="proposal_id" value="<?= $filter_proposal_id ?>">
                    <?php endif; ?>
                    <div class="filter-row">
                        <div class="filter-group">
                            <label for="keyword">Keyword</label>
                            <input type="text" id="keyword" name="keyword"
                                   value="<?= htmlspecialchars($search_keyword) ?>"
                                   placeholder="Search in comments...">
                        </div>

                        <div class="filter-group">
                            <label for="reviewer_id">From (Reviewer)</label>
                            <select id="reviewer_id" name="reviewer_id">
                                <option value="">All Reviewers</option>
                                <?php foreach ($reviewerList as $r): ?>
                                    <option value="<?= $r['user_id'] ?>" <?= $filter_reviewer_id === (int)$r['user_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($r['fname'] . ' ' . $r['lname']) ?> (<?= htmlspecialchars($r['clubrole']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="status">Status</label>
                            <select id="status" name="status">
                                <option value="">All Statuses</option>
                                <?php foreach (['Submitted', 'Under Review', 'Accepted', 'Rejected'] as $s): ?>
                                    <option value="<?= $s ?>" <?= $filter_status === $s ? 'selected' : '' ?>>
                                        <?= $s ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="date">Date</label>
                            <input type="date" id="date" name="date"
                                   value="<?= htmlspecialchars($filter_date) ?>">
                        </div>

                        <button type="submit" class="btn-search">
                            <i class="bi bi-search"></i> Search
                        </button>

                        <?php if ($search_keyword !== '' || $filter_status !== '' || $filter_date !== '' || $filter_reviewer_id > 0 || $filter_proposal_id > 0): ?>
                            <a href="remark-search.php" class="btn-clear">Clear</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- ── Results ── -->
            <?php $count = $results->num_rows; ?>
            <div class="results-count">
                <?php if ($search_keyword !== '' || $filter_status !== '' || $filter_date !== '' || $filter_reviewer_id > 0 || $filter_proposal_id > 0): ?>
                    Found <strong style="color:#fff;"><?= $count ?></strong> comment(s) matching your filters.
                <?php else: ?>
                    Showing all <strong style="color:#fff;"><?= $count ?></strong> comment(s).
                <?php endif; ?>
            </div>

            <?php if ($count > 0): ?>
                <?php while ($row = $results->fetch_assoc()): ?>
                    <div class="remark-card">
                        <div class="remark-card-header">
                            <a href="proposal-view.php?id=<?= $row['proposal_id'] ?>" class="proposal-title-link">
                                <i class="bi bi-file-text"></i>
                                <?= htmlspecialchars($row['proposal_title']) ?>
                            </a>
                            <?= statusBadge($row['status']) ?>
                        </div>

                        <div class="remark-meta">
                            <span>
                                <i class="bi bi-person-badge"></i>
                                <?= htmlspecialchars($row['reviewer_name']) ?>
                                (<?= htmlspecialchars($row['reviewer_clubrole']) ?>)
                            </span>
                            <span>
                                <i class="bi bi-person"></i>
                                Submitted by: <?= htmlspecialchars($row['submitter_name']) ?>
                            </span>
                            <span>
                                <i class="bi bi-clock"></i>
                                <?= date('d M Y, h:i A', strtotime($row['changed_at'])) ?>
                            </span>
                        </div>

                        <div class="remark-text">
                            <?= htmlspecialchars($row['remarks'] ?? 'No comments provided.') ?>
                        </div>

                        <?php $is_author = ((int)$row['reviewed_by'] === $session_user_id); ?>
                        <?php if ($is_author || $is_high_council): ?>
                            <div class="remark-actions">
                                <?php if ($is_author): ?>
                                    <a href="remark-edit.php?log_id=<?= $row['log_id'] ?>" class="btn-edit">
                                        <i class="bi bi-pencil"></i> Edit
                                    </a>
                                <?php endif; ?>
                                <?php if ($is_author || $is_high_council): ?>
                                    <a href="remark-delete.php?log_id=<?= $row['log_id'] ?>"
                                       class="btn-delete"
                                       onclick="return confirm('Delete this comment? This cannot be undone.');">
                                        <i class="bi bi-trash"></i> Delete
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="no-results">
                    <i class="bi bi-inbox" style="font-size:2rem;display:block;margin-bottom:8px;"></i>
                    No comments found.
                    <?php if ($search_keyword !== '' || $filter_status !== '' || $filter_date !== '' || $filter_reviewer_id > 0 || $filter_proposal_id > 0): ?>
                        Try adjusting your filters.
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <?php include('dashboard/dist/footer.php'); ?>
</div>

</body>
</html>