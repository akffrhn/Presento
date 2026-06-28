<?php
session_start();
include('dbcon.php');

/* ============================================================
   AUTH CHECKS — CYCOM only
============================================================ */
if (empty($_SESSION['user_id'])) {
    die("<script>alert('Please log in'); window.location.href='dashboard/dist/index.php';</script>");
}

$session_user_id  = (int)$_SESSION['user_id'];
$session_role     = $_SESSION['role'] ?? '';
$session_clubrole = $_SESSION['clubrole'] ?? '';

if ($session_role !== 'CYCOM') {
    die("<script>alert('You are not authorized.'); window.location.href='dashboard/dist/index.php';</script>");
}

$log_id = isset($_GET['log_id']) ? (int)$_GET['log_id'] : 0;
if ($log_id <= 0) {
    die("<script>alert('Invalid remark.'); window.location.href='proposal-list.php';</script>");
}

/* ============================================================
   FETCH EXISTING REMARK
============================================================ */
$stmt = $condb->prepare("
    SELECT psl.*, p.title AS proposal_title
    FROM proposal_status_log psl
    JOIN proposal p ON p.proposal_id = psl.proposal_id
    WHERE psl.log_id = ?
");
$stmt->bind_param("i", $log_id);
$stmt->execute();
$log = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$log) {
    die("<script>alert('Remark not found.'); window.location.href='proposal-list.php';</script>");
}

/* ============================================================
   OWNERSHIP CHECK — only the person who wrote it can edit
============================================================ */
if ((int)$log['reviewed_by'] !== $session_user_id) {
    die("<script>alert('You can only edit your own remarks.'); window.location.href='proposal-view.php?id=" . $log['proposal_id'] . "';</script>");
}

$errors = [];

/* ============================================================
   HANDLE FORM SUBMISSION
============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_status  = $_POST['status'] ?? '';
    $new_remarks = trim($_POST['remarks'] ?? '');

    if (!in_array($new_status, ['Submitted', 'Under Review', 'Accepted', 'Rejected'], true)) {
        $errors[] = "Please select a valid status.";
    }
    if ($new_remarks === '') {
        $errors[] = "Remarks cannot be empty.";
    }

    // Only High Council can set Accepted/Rejected
    if (empty($errors) && in_array($new_status, ['Accepted', 'Rejected']) && $session_clubrole !== 'High Council') {
        $errors[] = "Only High Council members can set Accepted or Rejected status.";
    }

    if (empty($errors)) {
        $upd = $condb->prepare("
            UPDATE proposal_status_log SET status = ?, remarks = ? WHERE log_id = ?
        ");
        $upd->bind_param("ssi", $new_status, $new_remarks, $log_id);

        if ($upd->execute()) {
            // Sync proposal status to this latest change
            $sync = $condb->prepare("UPDATE proposal SET status = ? WHERE proposal_id = ?");
            $sync->bind_param("si", $new_status, $log['proposal_id']);
            $sync->execute();
            $sync->close();
            $upd->close();
            header("Location: proposal-view.php?id=" . $log['proposal_id'] . "&flash=remark_updated");
            exit;
        } else {
            $errors[] = "Failed to update remark. Please try again.";
        }
        $upd->close();
    }

    // Keep new values on error
    $log['status']  = $new_status;
    $log['remarks'] = $new_remarks;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Remark — CYCOM E-Proposal</title>
    <link href="/Presento/assets/img/favicon.png" rel="icon">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/Presento/dashboard/dist/assets/css/mainc1.css">
    <link rel="stylesheet" href="/Presento/assets/css/style.css">
    <style>
        /* moved to assets/css/style.css */
        /* body background removed */

        .form-wrap { padding: 1.5rem; max-width: 620px; }
        .form-wrap h3 { color: #fff; font-weight: 700; margin-bottom: 1.25rem; }
        .proposal-ref {
            background: rgba(255,255,255,0.1);
            border-left: 4px solid #C89DB8;
            padding: 10px 14px;
            color: #f3e8ef;
            border-radius: 4px;
            margin-bottom: 1.2rem;
            font-size: 0.9rem;
        }
        .proposal-ref strong { color: #fff; }
        .edit-form {
            background: #5a1640;
            border: 2px solid #fff;
            border-radius: 8px;
            padding: 2rem;
        }
        .form-row { margin-bottom: 1.1rem; }
        .form-row label {
            display: block;
            color: #fff;
            font-weight: 600;
            margin-bottom: 6px;
            font-size: 0.9rem;
        }
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
        .form-row textarea { min-height: 120px; resize: vertical; }
        .btn-row {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 1.5rem;
        }
        .btn-cancel, .btn-submit {
            padding: 9px 20px;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            font-size: 0.95rem;
        }
        .btn-submit { background: #fff; color: #491231; }
        .btn-cancel { background: transparent; color: #fff; border: 1px solid #fff; }
        .error-box {
            background: #C89DB8;
            color: #491231;
            padding: 12px 16px;
            border-radius: 4px;
            margin-bottom: 1rem;
        }
        .error-box ul { margin: 0; padding-left: 1.2rem; }
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
        <div class="form-wrap">

            <a href="proposal-view.php?id=<?= $log['proposal_id'] ?>" class="btn-back">
                <i class="bi bi-arrow-left"></i> Back to Proposal
            </a>

            <h3>Edit Remark</h3>

            <div class="proposal-ref">
                <i class="bi bi-file-text"></i>
                Proposal: <strong><?= htmlspecialchars($log['proposal_title']) ?></strong>
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

            <form method="POST" action="" class="edit-form" novalidate id="editForm">

                <div class="form-row">
                    <label for="status">Status</label>
                    <select id="status" name="status" required title="Please select a status">
                        <?php foreach (['Submitted', 'Under Review', 'Accepted', 'Rejected'] as $s): ?>
                            <option value="<?= $s ?>" <?= $log['status'] === $s ? 'selected' : '' ?>>
                                <?= $s ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($session_clubrole !== 'High Council'): ?>
                        <small style="color:#d8c4d0;font-size:0.8rem;">
                            Only High Council can set Accepted / Rejected.
                        </small>
                    <?php endif; ?>
                </div>

                <div class="form-row">
                    <label for="remarks">Remarks</label>
                    <textarea id="remarks" name="remarks" required
                        title="Remarks cannot be empty"
                        placeholder="Enter your review remarks..."><?= htmlspecialchars($log['remarks'] ?? '') ?></textarea>
                </div>

                <div class="btn-row">
                    <a class="btn-cancel" href="proposal-view.php?id=<?= $log['proposal_id'] ?>">Cancel</a>
                    <button type="submit" class="btn-submit">Save Changes</button>
                </div>

            </form>
        </div>
    </div>

    <?php include('dashboard/dist/footer.php'); ?>
</div>

<script>
    const form = document.getElementById('editForm');
    form.addEventListener('submit', function (e) {
        if (!form.checkValidity()) {
            e.preventDefault();
            form.reportValidity();
        }
    });
</script>

</body>
</html>