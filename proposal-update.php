<?php
session_start();
include('dbcon.php');

/* ============================================================
   AUTH CHECKS — must be logged in
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

if ($session_role !== 'CYCOM' && $session_role !== 'Student') {
    die("<script>
            alert('You are not authorized to access this page');
            window.location.href='dashboard/dist/index.php';
         </script>");
}

/* ============================================================
   WHICH PROPOSAL ARE WE EDITING?
   - On GET (link from proposal-list): identified by ?proposal_id=...
   - On POST (form resubmit): identified by the hidden
     'current_proposal_id' field.
============================================================ */
$target_proposal_id = (int) ($_GET['proposal_id'] ?? $_POST['current_proposal_id'] ?? 0);

if ($target_proposal_id <= 0) {
    die("<script>
            alert('Missing proposal_id');
            window.location.href='proposal-list.php';
         </script>");
}

$errors = [];
$old = [
    'proposal_id'    => $target_proposal_id,
    'title'          => '',
    'objectives'     => '',
    'activities'     => '',
    'status'         => '',
    'date_submitted' => '',
];

/* ============================================================
   LOAD EXISTING PROPOSAL
============================================================ */
function get_proposal(mysqli $condb, int $proposal_id): ?array {
    $q = "SELECT proposal_id, user_id, title, objectives, activities, status, date_submitted FROM proposal WHERE proposal_id = ?";
    $s = $condb->prepare($q);
    $s->bind_param("i", $proposal_id);
    $s->execute();
    return $s->get_result()->fetch_assoc();
}

$existing_proposal = get_proposal($condb, $target_proposal_id);

if (!$existing_proposal) {
    die("<script>
            alert('Proposal not found');
            window.location.href='proposal-list.php';
         </script>");
}

// Ownership check — only the proposal owner or a CYCOM member may edit
$proposal_owner_id = (int) $existing_proposal['user_id'];
if ($proposal_owner_id !== $current_user_id && $session_role !== 'CYCOM') {
    die("<script>
            alert('You are not authorized to edit this proposal.');
            window.location.href='proposal-list.php';
         </script>");
}

// Pre-fill from the database record by default
$old['title']          = $existing_proposal['title'];
$old['objectives']     = $existing_proposal['objectives'];
$old['activities']     = $existing_proposal['activities'];
$old['status']         = $existing_proposal['status'];
$old['date_submitted'] = $existing_proposal['date_submitted'];

/* ============================================================
   HANDLE FORM SUBMISSION
============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Re-verify ownership on POST (prevents spoofed form submissions)
    if ((int) $existing_proposal['user_id'] !== $current_user_id && $session_role !== 'CYCOM') {
        die("<script>alert('You are not authorized to edit this proposal.');window.location.href='proposal-list.php';</script>");
    }


    $old['title']      = trim($_POST['title'] ?? '');
    $old['objectives'] = trim($_POST['objectives'] ?? '');
    $old['activities'] = trim($_POST['activities'] ?? '');
    $old['status']     = $_POST['status'] ?? '';

    // Basic validation
    if ($old['title'] === '') {
        $errors[] = "Title is required.";
    }
    if ($old['objectives'] === '') {
        $errors[] = "Objectives are required.";
    }
    if ($old['activities'] === '') {
        $errors[] = "Activities are required.";
    }

    $allowed_statuses = ['Submitted', 'Under Review', 'Accepted', 'Rejected'];
    if (!in_array($old['status'], $allowed_statuses, true)) {
        $errors[] = "Please select a valid status.";
    }

    // Only CYCOM can change status — but only on proposals they don't own.
    // When editing their OWN proposal, even CYCOM users act as the submitter.
    $is_own_proposal = ($proposal_owner_id === $current_user_id);
    if ($is_own_proposal && $old['status'] !== $existing_proposal['status']) {
        $errors[] = "You cannot change the status of your own proposal.";
    } elseif (!$is_own_proposal && $session_role !== 'CYCOM' && $old['status'] !== $existing_proposal['status']) {
        $errors[] = "You are not authorized to change the proposal status.";
    }

    // Update the proposal
    if (empty($errors)) {
        $query  = "UPDATE proposal SET title = ?, objectives = ?, activities = ?, status = ? WHERE proposal_id = ?";
        $update = $condb->prepare($query);
        $update->bind_param("ssssi", $old['title'], $old['objectives'], $old['activities'], $old['status'], $target_proposal_id);

        if ($update->execute()) {
            $update->close();
            header("Location: proposal-list.php?user_id=" . $current_user_id . "&flash=proposal_updated");
            exit;
        } else {
            $errors[] = "Failed to update proposal. Please try again.";
            $update->close();
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Update Proposal — CYCOM E-Proposal</title>

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

        .form-row input,
        .form-row select,
        .form-row textarea {
            width: 100%;
            padding: 8px 10px;
            border-radius: 4px;
            border: 1px solid #ccc;
            font-family: Arial, sans-serif;
            font-size: 0.95rem;
            box-sizing: border-box;
        }

        .form-row textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-row input:disabled,
        .form-row textarea:disabled {
            background: #e9e3e7;
            color: #777;
            cursor: not-allowed;
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

        .error-box {
            background: #C89DB8;
            color: #491231;
            padding: 12px 16px;
            border-radius: 4px;
            margin-bottom: 1rem;
        }

        .error-box ul {
            margin: 0;
            padding-left: 1.2rem;
        }

        .hint {
            color: #d8c4d0;
            font-size: 0.8rem;
            margin-top: 4px;
        }

        .meta-info {
            color: #d8c4d0;
            font-size: 0.85rem;
            margin-bottom: 1.25rem;
        }
    </style>
</head>

<body>

<?php include('dashboard/dist/navigation1.php'); ?>

<div class="main-wrap">
    <div class="content">

        <div class="form-wrap">

            <h3>Update Proposal</h3>

            <!-- Read-only meta info -->
            <div class="meta-info">
                <i class="bi bi-hash"></i> Proposal ID: <strong><?= htmlspecialchars($target_proposal_id) ?></strong>
                &nbsp;|&nbsp;
                <i class="bi bi-calendar3"></i> Submitted: <strong><?= htmlspecialchars($old['date_submitted']) ?></strong>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="error-box">
                    <ul>
                        <?php foreach ($errors as $err): ?>
                            <li><?= htmlspecialchars($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="" class="user-form" novalidate id="updateProposalForm">

                <input type="hidden" name="current_proposal_id" value="<?= htmlspecialchars($target_proposal_id) ?>">

                <!-- Title -->
                <div class="form-row">
                    <label for="title">Title</label>
                    <input
                        type="text"
                        id="title"
                        name="title"
                        value="<?= htmlspecialchars($old['title']) ?>"
                        placeholder="e.g. Annual Tech Symposium 2025"
                        required>
                </div>

                <!-- Objectives -->
                <div class="form-row">
                    <label for="objectives">Objectives</label>
                    <textarea
                        id="objectives"
                        name="objectives"
                        placeholder="Describe the objectives of the proposal..."
                        required><?= htmlspecialchars($old['objectives']) ?></textarea>
                </div>

                <!-- Activities -->
                <div class="form-row">
                    <label for="activities">Activities</label>
                    <textarea
                        id="activities"
                        name="activities"
                        placeholder="List the planned activities..."
                        required><?= htmlspecialchars($old['activities']) ?></textarea>
                </div>

                <!-- Status — editable only by CYCOM reviewing someone else's proposal -->
                <?php
                    $own_proposal      = ($proposal_owner_id === $current_user_id);
                    $can_change_status = ($session_role === 'CYCOM' && !$own_proposal);
                ?>
                <div class="form-row">
                    <label for="status">Status</label>
                    <select
                        id="status"
                        name="status"
                        <?= !$can_change_status ? 'disabled' : '' ?>
                        required>
                        <option value="" disabled <?= $old['status'] === '' ? 'selected' : '' ?>>Select status</option>
                        <option value="Submitted"    <?= $old['status'] === 'Submitted'    ? 'selected' : '' ?>>Submitted</option>
                        <option value="Under Review" <?= $old['status'] === 'Under Review' ? 'selected' : '' ?>>Under Review</option>
                        <option value="Accepted"     <?= $old['status'] === 'Accepted'     ? 'selected' : '' ?>>Accepted</option>
                        <option value="Rejected"     <?= $old['status'] === 'Rejected'     ? 'selected' : '' ?>>Rejected</option>
                    </select>
                    <?php if (!$can_change_status): ?>
                        <input type="hidden" name="status" value="<?= htmlspecialchars($old['status']) ?>">
                        <div class="hint">
                            <?= $own_proposal
                                ? 'You cannot change the status of your own proposal.'
                                : 'Only CYCOM members can change the proposal status.' ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="btn-row">
                    <a class="btn-cancel" href="proposal-list.php?user_id=<?= $current_user_id ?>">Cancel</a>
                    <button type="submit" class="btn-submit">Save Changes</button>
                </div>

            </form>

        </div><!-- /form-wrap -->
    </div><!-- /content -->

    <?php include('dashboard/dist/footer.php'); ?>

</div><!-- /main-wrap -->

<script>
    const form = document.getElementById('updateProposalForm');

    form.addEventListener('submit', function (e) {
        if (!form.checkValidity()) {
            e.preventDefault();
            form.reportValidity();
        }
    });
</script>

</body>
</html>