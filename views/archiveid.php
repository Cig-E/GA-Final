<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Archive Details</title>
    <link rel="stylesheet" href="/archive-project/views/archiveid.css">
</head>
<body>
<div class="container">
    <a href="/archive-project/archives" class="back-link">← Back to Archives</a>

    <div class="archive-card">
        <h1>Archive Details</h1>

        <a href="<?= htmlspecialchars($archive['url']) ?>" target="_blank" class="archive-url">
            <?= htmlspecialchars($archive['url']) ?>
        </a>

        <?php if ($archive['description']): ?>
            <div class="description"><?= nl2br(htmlspecialchars($archive['description'])) ?></div>
        <?php endif ?>

        <div class="meta-grid">
            <div class="meta-item">
                <strong>Status</strong>
                <span class="status status-<?= $archive['status'] ?>"><?= ucfirst($archive['status']) ?></span>
            </div>
            <div class="meta-item">
                <strong>Submitted</strong>
                <?= date('M d, Y', strtotime($archive['submitted_at'])) ?>
            </div>
            <div class="meta-item">
                <strong>Downloads</strong>
                <?= number_format($archive['download_count']) ?>
            </div>
            <?php if ($archive['torrent_file_size']): ?>
            <div class="meta-item">
                <strong>Torrent Size</strong>
                <?= number_format($archive['torrent_file_size'] / 1024, 2) ?> KB
            </div>
            <?php endif ?>
            <?php if ($archive['archive_size']): ?>
            <div class="meta-item">
                <strong>Archive Size</strong>
                <?= number_format($archive['archive_size'] / 1024 / 1024, 2) ?> MB
            </div>
            <?php endif ?>
            <?php if ($reviews): ?>
            <div class="meta-item">
                <strong>Rating</strong>
                <span class="rating">
                    <?= str_repeat('★', round($avg_rating)) . str_repeat('☆', 5 - round($avg_rating)) ?>
                    (<?= number_format($avg_rating, 1) ?>)
                </span>
            </div>
            <?php endif ?>
        </div>

        <div class="submitter-info">
            <div class="profile-pic">
                <?php if ($archive['profile_pic_path']): ?>
                    <img src="<?= htmlspecialchars($archive['profile_pic_path']) ?>"
                         style="width:100%;height:100%;border-radius:50%;object-fit:cover">
                <?php else: ?>
                    👤
                <?php endif ?>
            </div>
            <div>
                <strong>Submitted by:
                    <a href="/archive-project/user/<?= urlencode($archive['username']) ?>">
                        <?= htmlspecialchars($archive['username'] ?? 'Anonymous') ?>
                    </a>
                </strong>
                <?php if ($archive['bio']): ?>
                    <div style="color:#666;font-size:14px"><?= htmlspecialchars($archive['bio']) ?></div>
                <?php endif ?>
            </div>
        </div>

        <?php if ($archive['torrent_path']): ?>
        <div class="download-section">
            <h3>📥 Download Torrent</h3>
            <p>Download the .torrent file to start seeding and preserve this archive</p>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="/archive-project/download/<?= basename($archive['torrent_path']) ?>" class="download-btn">
                    Download Torrent File
                </a>
            <?php else: ?>
                <div class="login-prompt">
                    <p>You must be logged in to download torrents</p>
                    <a href="/archive-project/login" class="download-btn" style="background:#007bff">Login to Download</a>
                </div>
            <?php endif ?>
        </div>
        <?php endif ?>
    </div>

    <div class="reviews-section">
        <div class="reviews-header">
            <h2>Reviews (<?= count($reviews) ?>)</h2>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="/archive-project/review/<?= $id ?>" class="add-review-btn">Write a Review</a>
            <?php endif ?>
        </div>

        <?php if (empty($reviews)): ?>
            <div class="no-reviews"><p>No reviews yet. Be the first!</p></div>
        <?php else: foreach ($reviews as $r): ?>
            <div class="review-item">
                <div class="review-header">
                    <span class="review-author"><?= htmlspecialchars($r['username'] ?? 'Anonymous') ?></span>
                    <span class="review-date"><?= date('M d, Y', strtotime($r['reviewed_at'])) ?></span>
                </div>
                <div class="rating">
                    <?= str_repeat('★', $r['rating']) . str_repeat('☆', 5 - $r['rating']) ?>
                </div>
                <?php if ($r['comment']): ?>
                    <div class="review-comment"><?= nl2br(htmlspecialchars($r['comment'])) ?></div>
                <?php endif ?>
            </div>
        <?php endforeach; endif ?>
    </div>
</div>
</body>
</html>