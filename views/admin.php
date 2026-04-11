<?php
// POST handler 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action    = $_POST['action']    ?? '';
    $target_id = (int) ($_POST['target_id'] ?? 0);

    switch ($action) {
        case 'approve_archive':
            $pdo->prepare("UPDATE archives SET status='approved' WHERE id=?")->execute([$target_id]);
            break;
        case 'reject_archive':
            $pdo->prepare("UPDATE archives SET status='rejected' WHERE id=?")->execute([$target_id]);
            break;
        case 'delete_archive':
            $row = $pdo->prepare("SELECT torrent_path FROM archives WHERE id=?");
            $row->execute([$target_id]);
            $path = $row->fetchColumn();
            if ($path && file_exists(__DIR__ . '/../' . $path)) unlink(__DIR__ . '/../' . $path);
            $pdo->prepare("DELETE FROM archives WHERE id=?")->execute([$target_id]);
            break;
        case 'ban_user':
            $pdo->prepare("UPDATE users SET is_active=false WHERE id=? AND username != 'admin'")
                ->execute([$target_id]);
            break;
        case 'unban_user':
            $pdo->prepare("UPDATE users SET is_active=true WHERE id=?")->execute([$target_id]);
            break;
        case 'promote_admin':
            $pdo->prepare("UPDATE users SET role='admin' WHERE id=?")->execute([$target_id]);
            break;
        case 'demote_user':
            $pdo->prepare("UPDATE users SET role='user' WHERE id=? AND username != 'admin'")
                ->execute([$target_id]);
            break;
        case 'delete_user':
            $pdo->prepare("DELETE FROM users WHERE id=? AND role != 'admin'")->execute([$target_id]);
            break;
    }

    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// Stats 
$stats = $pdo->query("
    SELECT
        COUNT(*) FILTER (WHERE status='pending')  AS pending,
        COUNT(*) FILTER (WHERE status='approved') AS approved,
        COUNT(*) FILTER (WHERE status='rejected') AS rejected,
        COUNT(*)                                  AS total
    FROM archives
")->fetch();

$user_stats = $pdo->query("
    SELECT
        COUNT(*)                              AS total,
        COUNT(*) FILTER (WHERE NOT is_active) AS banned,
        COUNT(*) FILTER (WHERE role='admin')  AS admins
    FROM users
")->fetch();

$archives = $pdo->query("
    SELECT a.*, u.username FROM archives a
    LEFT JOIN users u ON a.user_id = u.id
    ORDER BY a.submitted_at DESC
")->fetchAll();

$users = $pdo->query("
    SELECT id, username, email, role, is_active, created_at
    FROM users ORDER BY created_at DESC
")->fetchAll();

$tab = $_GET['tab'] ?? 'archives';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Panel</title>
    <link rel="stylesheet" href="/archive-project/views/admin.css">
</head>
<body>

<header>
    <h2>Admin Panel</h2>
    <a href="/archive-project/dashboard">← Dashboard</a>
</header>

<div class="stats">
    <?php foreach ([
        'Total Archives' => $stats['total'],
        'Pending'        => $stats['pending'],
        'Approved'       => $stats['approved'],
        'Rejected'       => $stats['rejected'],
        'Total Users'    => $user_stats['total'],
        'Admins'         => $user_stats['admins'],
        'Banned'         => $user_stats['banned'],
    ] as $label => $n): ?>
    <div class="stat-card">
        <div class="number"><?= $n ?></div>
        <div class="label"><?= $label ?></div>
    </div>
    <?php endforeach ?>
</div>

<div class="tabs">
    <a href="?tab=archives" class="<?= $tab === 'archives' ? 'active' : '' ?>">Archives</a>
    <a href="?tab=users"    class="<?= $tab === 'users'    ? 'active' : '' ?>">Users</a>
</div>

<div class="content">

<?php if ($tab === 'archives'): ?>
<table>
    <thead>
        <tr><th>ID</th><th>URL / Torrent</th><th>Submitted by</th><th>Date</th>
            <th>Size</th><th>Downloads</th><th>Status</th><th>Actions</th></tr>
    </thead>
    <tbody>
    <?php if (empty($archives)): ?>
        <tr><td colspan="8" class="no-data">No archives yet.</td></tr>
    <?php else: foreach ($archives as $a): ?>
        <tr>
            <td><?= $a['id'] ?></td>
            <td class="url-cell">
                <?php if ($a['url']): ?>
                    <a href="<?= htmlspecialchars($a['url']) ?>" target="_blank"><?= htmlspecialchars($a['url']) ?></a>
                <?php endif ?>
                <?php if ($a['torrent_path']): ?>
                    <a class="torrent-link" href="<?= htmlspecialchars($a['torrent_path']) ?>" download> Torrent</a>
                <?php endif ?>
            </td>
            <td><?= htmlspecialchars($a['username'] ?? 'deleted') ?></td>
            <td><?= date('Y-m-d H:i', strtotime($a['submitted_at'])) ?></td>
            <td><?= $a['archive_size'] ? number_format($a['archive_size'] / 1048576, 1) . ' MB' : '—' ?></td>
            <td><?= $a['download_count'] ?></td>
            <td><span class="badge <?= $a['status'] ?>"><?= ucfirst($a['status']) ?></span></td>
            <td>
                <?php if ($a['status'] === 'pending'): ?>
                    <?php foreach (['approve_archive' => 'Approve', 'reject_archive' => 'Reject'] as $act => $label): ?>
                    <form class="inline" method="POST">
                        <input type="hidden" name="action"    value="<?= $act ?>">
                        <input type="hidden" name="target_id" value="<?= $a['id'] ?>">
                        <button class="btn-<?= explode('_', $act)[0] ?>"><?= $label ?></button>
                    </form>
                    <?php endforeach ?>
                <?php endif ?>
                <form class="inline" method="POST" onsubmit="return confirm('Delete this archive?')">
                    <input type="hidden" name="action"    value="delete_archive">
                    <input type="hidden" name="target_id" value="<?= $a['id'] ?>">
                    <button class="btn-delete">Delete</button>
                </form>
            </td>
        </tr>
    <?php endforeach; endif ?>
    </tbody>
</table>

<?php elseif ($tab === 'users'): ?>
<table>
    <thead>
        <tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th>Joined</th><th>Actions</th></tr>
    </thead>
    <tbody>
    <?php if (empty($users)): ?>
        <tr><td colspan="7" class="no-data">No users yet.</td></tr>
    <?php else: foreach ($users as $u):
        $is_self  = $u['id'] === $_SESSION['user_id'];
        $is_root  = $u['username'] === 'admin';
        $is_admin = $u['role'] === 'admin';
    ?>
        <tr>
            <td><?= $u['id'] ?></td>
            <td><?= htmlspecialchars($u['username']) ?></td>
            <td><?= htmlspecialchars($u['email'] ?? '—') ?></td>
            <td><span class="badge <?= $u['role'] ?>"><?= ucfirst($u['role']) ?></span></td>
            <td><span class="badge <?= $u['is_active'] ? 'active' : 'banned' ?>"><?= $u['is_active'] ? 'Active' : 'Banned' ?></span></td>
            <td><?= date('Y-m-d', strtotime($u['created_at'])) ?></td>
            <td>
                <?php if ($is_self): ?>
                    <span style="color:#aaa;font-size:.8rem">You</span>
                <?php else: ?>
                    <?php if (!$is_root): ?>
                        <?php if ($u['is_active']): ?>
                        <form class="inline" method="POST" onsubmit="return confirm('Ban this user?')">
                            <input type="hidden" name="action"    value="ban_user">
                            <input type="hidden" name="target_id" value="<?= $u['id'] ?>">
                            <button class="btn-ban">Ban</button>
                        </form>
                        <?php else: ?>
                        <form class="inline" method="POST">
                            <input type="hidden" name="action"    value="unban_user">
                            <input type="hidden" name="target_id" value="<?= $u['id'] ?>">
                            <button class="btn-unban">Unban</button>
                        </form>
                        <?php endif ?>
                    <?php endif ?>

                    <?php if (!$is_admin): ?>
                        <form class="inline" method="POST" onsubmit="return confirm('Promote to admin?')">
                            <input type="hidden" name="action"    value="promote_admin">
                            <input type="hidden" name="target_id" value="<?= $u['id'] ?>">
                            <button class="btn-promote">Promote</button>
                        </form>
                        <form class="inline" method="POST" onsubmit="return confirm('Delete this user?')">
                            <input type="hidden" name="action"    value="delete_user">
                            <input type="hidden" name="target_id" value="<?= $u['id'] ?>">
                            <button class="btn-delete">Delete</button>
                        </form>
                    <?php elseif (!$is_root): ?>
                        <form class="inline" method="POST" onsubmit="return confirm('Demote this admin?')">
                            <input type="hidden" name="action"    value="demote_user">
                            <input type="hidden" name="target_id" value="<?= $u['id'] ?>">
                            <button class="btn-demote">Demote</button>
                        </form>
                    <?php endif ?>
                <?php endif ?>
            </td>
        </tr>
    <?php endforeach; endif ?>
    </tbody>
</table>
<?php endif ?>

</div>
</body>
</html>