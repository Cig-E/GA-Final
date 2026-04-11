<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Archives</title>
    <link rel="stylesheet" href="/archive-project/views/my-archives.css">
</head>
<body>
<div class="container">
    <a href="/archive-project/dashboard" class="back-link">← Back to Dashboard</a>

    <header>
        <div>
            <h1>My Archives</h1>
            <p><?= count($archives) ?> archive<?= count($archives) !== 1 ? 's' : '' ?></p>
        </div>
        <a href="/archive-project/submit" class="submit-btn">+ Submit New Archive</a>
    </header>

    <?php if (empty($archives)): ?>
        <div class="no-archives">
            <h2>No archives yet</h2>
            <a href="/archive-project/submit" class="submit-btn2">Submit Your First Archive</a>
        </div>
    <?php else: ?>
        <?php foreach ($archives as $a): ?>
        <div class="archive-card">
            <div class="archive-header">
                <a href="/archive-project/archive/<?= $a['id'] ?>" class="archive-url">
                    <?= htmlspecialchars($a['url']) ?>
                </a>
                <div class="actions">
                    <a href="/archive-project/edit-archive/<?= $a['id'] ?>" class="btn btn-edit">Edit</a>
                    <button onclick="deleteArchive(<?= $a['id'] ?>)" class="btn btn-delete">Delete</button>
                </div>
            </div>

            <?php if ($a['description']): ?>
                <div class="description"><?= nl2br(htmlspecialchars($a['description'])) ?></div>
            <?php endif ?>

            <div class="archive-meta">
                <span>Submitted: <?= date('M d, Y', strtotime($a['submitted_at'])) ?></span>
                <span>Downloads: <?= number_format($a['download_count']) ?></span>
                <span class="status status-<?= $a['status'] ?>"><?= ucfirst($a['status']) ?></span>
                <?php if ($a['torrent_path']): ?><span>Torrent uploaded</span><?php endif ?>
            </div>
        </div>
        <?php endforeach ?>
    <?php endif ?>
</div>

<script>
function deleteArchive(id) {
    if (!confirm('Delete this archive? This cannot be undone.')) return;
    fetch('/archive-project/delete-archive/' + id, { method: 'POST' })
        .then(() => location.reload())
        .catch(() => alert('Error deleting archive'));
}
</script>
</body>
</html>