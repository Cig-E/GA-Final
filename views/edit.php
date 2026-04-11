<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Archive</title>
    <link rel="stylesheet" href="/archive-project/views/edit.css">
</head>
<body>
<div class="container">
    <a href="/archive-project/my-archives" class="back-link">← Back to My Archives</a>

    <div class="edit-card">
        <h1>Edit Archive</h1>

        <form method="POST" action="/archive-project/edit-archive/<?= $id ?>" enctype="multipart/form-data">
            <label>URL *</label>
            <input type="url" name="url" value="<?= htmlspecialchars($archive['url']) ?>" required>

            <label>Description <em>(optional)</em></label>
            <textarea name="description" placeholder="What is this archive about?"><?= htmlspecialchars($archive['description'] ?? '') ?></textarea>

            <label>Replace Torrent <em>(optional)</em></label>
            <input type="file" name="torrent" accept=".torrent">

            <?php if ($archive['torrent_path']): ?>
            <p class="info">
                Current: <?= basename($archive['torrent_path']) ?>
                (<?= number_format($archive['torrent_file_size'] / 1024, 2) ?> KB) —
                leave blank to keep it
            </p>
            <?php else: ?>
            <p class="info">Max 2 MB · .torrent only</p>
            <?php endif ?>

            <button type="submit">Save Changes</button>
        </form>
    </div>
</div>
</body>
</html>