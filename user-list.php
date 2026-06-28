<?php
session_start();
include('dbcon.php');

// Same fixed ID used for the placeholder "Deleted User" account in userdelete.php
define('DELETED_USER_ID', 0);

if (empty($_SESSION['user_id'])) {
    die("<script>
            alert('Please login first');
            window.location.href='login/index.php';
         </script>");
}

$current_user_id = (int) $_SESSION['user_id'];



// Fetch the current logged-in user
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

$session_role      = strtolower((string) ($_SESSION['role'] ?? ''));
$session_club_role = trim((string) ($_SESSION['clubrole'] ?? ''));
$is_authorized_admin = in_array($session_role, ['admin', 'cycom'], true);

if (!$is_authorized_admin) {
    die("<script>
            alert('Unauthorized access');
            window.location.href='dashboard/dist/index.php';
         </script>");
}

// Only High Council CYCOM members may add new users
$is_high_council = ($session_role === 'cycom' && $session_club_role === 'High Council');

// Fetch ALL users for the table, excluding the placeholder "Deleted User" account
// Optional search by specific column or all columns
$search     = trim($_GET['search'] ?? '');
$search_col = $_GET['search_col'] ?? 'all';
$deleted_id = DELETED_USER_ID;

$allowed_cols = [
    'all'      => null,
    'name'     => "CONCAT(fname, ' ', lname)",
    'email'    => 'email',
    'matric'   => 'CAST(user_id AS CHAR)',
    'phone'    => 'phonenum',
    'role'     => 'role',
    'clubrole' => 'clubrole',
];

// Silently fall back to 'all' if an unexpected column is supplied
if (!array_key_exists($search_col, $allowed_cols)) {
    $search_col = 'all';
}

if ($search !== '') {
    $likeTerm = '%' . $search . '%';

    if ($search_col === 'all') {
        $stmt_users = $condb->prepare(
            "SELECT * FROM user
             WHERE user_id != ?
               AND (CONCAT(fname, ' ', lname) LIKE ?
                    OR email                  LIKE ?
                    OR CAST(user_id AS CHAR)  LIKE ?
                    OR phonenum               LIKE ?
                    OR role                   LIKE ?
                    OR clubrole               LIKE ?)
             ORDER BY fname ASC"
        );
        $stmt_users->bind_param(
            "issssss",
            $deleted_id,
            $likeTerm, $likeTerm, $likeTerm,
            $likeTerm, $likeTerm, $likeTerm
        );
    } else {
        $col_expr = $allowed_cols[$search_col]; // safe: comes from our own whitelist
        $stmt_users = $condb->prepare(
            "SELECT * FROM user
             WHERE user_id != ?
               AND {$col_expr} LIKE ?
             ORDER BY fname ASC"
        );
        $stmt_users->bind_param("is", $deleted_id, $likeTerm);
    }
} else {
    $stmt_users = $condb->prepare("SELECT * FROM user WHERE user_id != ? ORDER BY fname ASC");
    $stmt_users->bind_param("i", $deleted_id);
}

$stmt_users->execute();
$users = $stmt_users->get_result();
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>User List — CYCOM E-User</title>

    <link href="/Presento/assets/img/favicon.png" rel="icon">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/Presento/dashboard/dist/assets/css/mainc1.css">
    <link rel="stylesheet" href="/Presento/assets/css/style.css">

    <style>
        body {
            background: #491231;
        }

        .user-wrap {
            padding: 1.5rem;
        }

        .user-wrap h3 {
            color: #fff;
            font-weight: 700;
            margin-bottom: 1.25rem;
        }

        .user-table {
            width: 100%;
            border-collapse: collapse;
        }

        .user-table th,
        .user-table td {
            border: 1px solid #fff;
            padding: 10px 16px;
            color: #fff;
            text-align: left;
        }

        .user-table th {
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

        .user-table a {
            color: #fff;
            text-decoration: underline;
        }

        .user-table a:hover {
            opacity: 0.8;
        }

        .new-user-btn {
            display: inline-block;
            color: #fff;
            text-decoration: underline;
        }

        .user-toolbar {
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
            align-items: center;
            flex-wrap: wrap;
        }

        .search-form select {
            padding: 6px 10px;
            border-radius: 4px;
            border: 1px solid #ccc;
            background: #fff;
            color: #491231;
            font-weight: 600;
            cursor: pointer;
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
    </style>
</head>

<body>

<?php include('dashboard/dist/navigation1.php'); ?>

<div class="main-wrap">
    <div class="content">

        <div class="user-wrap">

            <h3>User List</h3>

            <div class="user-toolbar">

                <form method="GET" action="" class="search-form">

                    <select name="search_col">
                        <?php
                        $col_labels = [
                            'all'      => 'All columns',
                            'name'     => 'Name',
                            'email'    => 'Email',
                            'matric'   => 'Matric number',
                            'phone'    => 'Phone number',
                            'role'     => 'Role',
                            'clubrole' => 'Club role',
                        ];
                        foreach ($col_labels as $val => $label): ?>
                            <option value="<?= $val ?>"
                                <?= ($search_col === $val) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <input
                        type="text"
                        name="search"
                        value="<?= htmlspecialchars($search) ?>"
                        placeholder="Enter search term…"
                    >
                    <button type="submit">Search</button>

                    <?php if ($search !== ''): ?>
                        <a href="user-list.php" class="clear-link">Clear</a>
                    <?php endif; ?>

                </form>

                <?php if ($is_high_council): ?>
                    <a class="new-user-btn" href="user-register.php?user_id=<?= htmlspecialchars($user['user_id']) ?>">
                        + New User
                    </a>
                <?php endif; ?>

            </div>

           <table class="user-table">
    <thead>
        <tr>
            <th style="width:40px;">No</th>
            <th style="width:60px;">Matric Number</th>
            <th>Name</th>
            <th>Email</th>
            <th>Phone Number</th>
            <th>Role</th>
            <th>Club Role</th>
            <th style="width:160px;">Action</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($users->num_rows > 0): ?>
            <?php $no = 1; while ($row = $users->fetch_assoc()): ?>
                <tr>
                    <td><?= $no++ ?></td>
                    <td><?= htmlspecialchars($row['user_id']) ?></td>
                    <td><?= htmlspecialchars($row['fname'] . ' ' . $row['lname']) ?></td>
                    <td><?= htmlspecialchars($row['email']) ?></td>
                    <td><?= htmlspecialchars($row['phonenum']) ?></td>
                    <td><?= htmlspecialchars($row['role']) ?></td>
                    <td><?= htmlspecialchars($row['clubrole']) ?></td>
                    <td>
                        <?php if ($is_high_council): ?>
                            <a href="user-update.php?user_id=<?= htmlspecialchars($row['user_id']) ?>">Update</a>
                            |
                            <a href="userdelete.php?user_id=<?= htmlspecialchars($row['user_id']) ?>">Delete</a>
                        <?php else: ?>
                            &mdash;
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="8" style="text-align:center;">
                    <?php if ($search !== ''): ?>
                        No users found matching "<?= htmlspecialchars($search) ?>"
                        <?php if ($search_col !== 'all'): ?>
                            in <strong><?= htmlspecialchars($col_labels[$search_col]) ?></strong>
                        <?php endif; ?>.
                    <?php else: ?>
                        No users submitted yet.
                    <?php endif; ?>
                </td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

        </div><!-- /user-wrap -->
    </div><!-- /content -->

    <?php include('dashboard/dist/footer.php'); ?>

</div><!-- /main-wrap -->

</body>
</html>