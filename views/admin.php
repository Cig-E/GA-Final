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

    // 1. Specific Logic: File deletion (only for delete_archive)
    if ($action === 'delete_archive') {
        $stmt = $pdo->prepare("SELECT torrent_path FROM archives WHERE id=?");
        $stmt->execute([$id]);
        if ($path = $stmt->fetchColumn()) @unlink(__DIR__ . '/../' . $path);
    }

    // 2. Generic Logic: Execute the mapped query
    if (isset($queries[$action])) {
        $pdo->prepare($queries[$action])->execute([$id]);
    }

    header("Location: {$_SERVER['REQUEST_URI']}");
    exit;
}

// Fetching data
$tab = $_GET['tab'] ?? 'archives';
$stats = $pdo->query("SELECT status, COUNT(*) as count FROM archives GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
$data = ($tab === 'users') 
    ? $pdo->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll()
    : $pdo->query("SELECT a.*, u.username FROM archives a LEFT JOIN users u ON a.user_id = u.id ORDER BY a.submitted_at DESC")->fetchAll();

// UI Helper Function
function actionBtn($action, $id, $label, $class, $confirm = false) {
    $js = $confirm ? "onsubmit=\"return confirm('$confirm')\"" : "";
    return "
    <form class='inline' method='POST' $js>
        <input type='hidden' name='action' value='$action'>
        <input type='hidden' name='target_id' value='$id'>
        <button class='btn-$class'>$label</button>
    </form>";
}
?>

<div class="content">
    <table>
    <?php if ($tab === 'archives'): ?>
        <tr><th>File</th><th>User</th><th>Status</th><th>Actions</th></tr>
        <?php foreach ($data as $a): ?>
            <tr>
                <td><?= htmlspecialchars($a['url'] ?: $a['torrent_path']) ?></td>
                <td><?= htmlspecialchars($a['username'] ?? 'deleted') ?></td>
                <td><span class="badge <?= $a['status'] ?>"><?= $a['status'] ?></span></td>
                <td>
                    <?= ($a['status'] === 'pending') ? 
                        actionBtn('approve_archive', $a['id'], 'Approve', 'approve') . 
                        actionBtn('reject_archive', $a['id'], 'Reject', 'reject') : '' ?>
                    <?= actionBtn('delete_archive', $a['id'], 'Delete', 'delete', 'Delete archive?') ?>
                </td>
            </tr>
        <?php endforeach; ?>

    <?php else: ?>
        <tr><th>Username</th><th>Role</th><th>Status</th><th>Actions</th></tr>
        <?php foreach ($data as $u): ?>
            <?php if ($u['id'] === ($_SESSION['user_id'] ?? 0)) continue; ?>
            <tr>
                <td><?= htmlspecialchars($u['username']) ?></td>
                <td><?= $u['role'] ?></td>
                <td><?= $u['is_active'] ? 'Active' : 'Banned' ?></td>
                <td>
                    <?php if ($u['username'] !== 'admin'): ?>
                        <?= $u['is_active'] ? actionBtn('ban_user', $u['id'], 'Ban', 'ban', 'Ban?') : actionBtn('unban_user', $u['id'], 'Unban', 'unban') ?>
                        <?= $u['role'] === 'admin' ? actionBtn('demote_user', $u['id'], 'Demote', 'demote') : actionBtn('promote_admin', $u['id'], 'Promote', 'promote') ?>
                        <?= actionBtn('delete_user', $u['id'], 'Delete', 'delete', 'Delete user?') ?>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>
    </table>
</div>