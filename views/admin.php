<?php
// POST handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['target_id'] ?? 0);

    // Map actions to SQL queries
    $queries = [
        'approve_archive' => "UPDATE archives SET status='approved' WHERE id=?",
        'reject_archive'  => "UPDATE archives SET status='rejected' WHERE id=?",
        'unban_user'      => "UPDATE users SET is_active=true WHERE id=?",
        'promote_admin'   => "UPDATE users SET role='admin' WHERE id=?",
        'demote_user'     => "UPDATE users SET role='user' WHERE id=? AND username != 'admin'",
        'delete_user'     => "DELETE FROM users WHERE id=? AND role != 'admin'",
        'ban_user'        => "UPDATE users SET is_active=false WHERE id=? AND username != 'admin'",
        'delete_archive'  => "DELETE FROM archives WHERE id=?"
    ];

    if ($action === 'delete_archive') {
        $stmt = $pdo->prepare("SELECT torrent_path FROM archives WHERE id=?");
        $stmt->execute([$id]);
        if ($path = $stmt->fetchColumn()) @unlink(__DIR__ . '/../' . $path);
    }

    if (isset($queries[$action])) {
        $pdo->prepare($queries[$action])->execute([$id]);
    }

    header("Location: {$_SERVER['REQUEST_URI']}");
    exit;
}

// Fetch Stats & Data (Combined logic)
$stats = $pdo->query("SELECT status, COUNT(*) as count FROM archives GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
$user_stats = $pdo->query("SELECT role, is_active, COUNT(*) as count FROM users GROUP BY role, is_active")->fetchAll();

$tab = $_GET['tab'] ?? 'archives';
$data = ($tab === 'users') 
    ? $pdo->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll()
    : $pdo->query("SELECT a.*, u.username FROM archives a LEFT JOIN users u ON a.user_id = u.id ORDER BY a.submitted_at DESC")->fetchAll();

// Helper for UI buttons
function btn($action, $id, $label, $class, $confirm = false) {
    $js = $confirm ? "onsubmit=\"return confirm('$confirm')\"" : "";
    return "<form class='inline' method='POST' $js>
                <input type='hidden' name='action' value='$action'>
                <input type='hidden' name='target_id' value='$id'>
                <button class='btn-$class'>$label</button>
            </form>";
}
?>

<div class="stats">
    <?php foreach(['Pending' => $stats['pending'] ?? 0, 'Total' => array_sum($stats)] as $label => $val): ?>
        <div class="stat-card"><b><?= $val ?></b><br><?= $label ?></div>
    <?php endforeach; ?>
</div>

<div class="tabs">
    <a href="?tab=archives" class="<?= $tab != 'users' ? 'active' : '' ?>">Archives</a>
    <a href="?tab=users" class="<?= $tab == 'users' ? 'active' : '' ?>">Users</a>
</div>

<table>
    <?php if ($tab === 'archives'): ?>
        <tr><th>ID</th><th>File</th><th>User</th><th>Status</th><th>Actions</th></tr>
        <?php foreach ($data as $a): ?>
            <tr>
                <td><?= $a['id'] ?></td>
                <td><?= $a['url'] ?: $a['torrent_path'] ?></td>
                <td><?= htmlspecialchars($a['username'] ?? 'deleted') ?></td>
                <td><span class="badge <?= $a['status'] ?>"><?= $a['status'] ?></span></td>
                <td>
                    <?php if ($a['status'] === 'pending') {
                        echo btn('approve_archive', $a['id'], 'Approve', 'approve');
                        echo btn('reject_archive', $a['id'], 'Reject', 'reject');
                    }
                    echo btn('delete_archive', $a['id'], 'Delete', 'delete', 'Delete archive?'); ?>
                </td>
            </tr>
        <?php endforeach; ?>
    <?php else: ?>
        <tr><th>User</th><th>Role</th><th>Status</th><th>Actions</th></tr>
        <?php foreach ($data as $u): 
            if ($u['id'] === $_SESSION['user_id']) continue; ?>
            <tr>
                <td><?= htmlspecialchars($u['username']); ?></td>
                <td><?= $u['role'] ?></td>
                <td><?= $u['is_active'] ? 'Active' : 'Banned' ?></td>
                <td>
                    <?php 
                    if ($u['username'] !== 'admin') {
                        echo $u['is_active'] ? btn('ban_user', $u['id'], 'Ban', 'ban', 'Ban user?') : btn('unban_user', $u['id'], 'Unban', 'unban');
                        echo $u['role'] === 'admin' ? btn('demote_user', $u['id'], 'Demote', 'demote') : btn('promote_admin', $u['id'], 'Promote', 'promote');
                        echo btn('delete_user', $u['id'], 'Delete', 'delete', 'Delete user?');
                    } ?>
                </td>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>
</table>