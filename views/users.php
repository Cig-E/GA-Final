<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($user['username']) ?>'s Profile</title>
    <link rel="stylesheet" href="/archive-project/views/user.css">
</head>
<body>
<div class="container">
    <a href="/archive-project/archives" class="back-link">← Back to Archives</a>

    <div class="profile-header">
        <div class="profile-pic-large">
            <?php if ($user['profile_pic_path']): ?>
                <img src="<?= htmlspecialchars($user['profile_pic_path']) ?>" alt="Profile">
            <?php else: ?>
                👤
            <?php endif ?>
        </div>
        <div class="profile-info">
            <h1 class="username">
                <?= htmlspecialchars($user['username']) ?>
                <span class="role-badge role-<?= $user['role'] ?>"><?= $user['role'] ?></span>
                <?php if (!$user['is_active']): ?>
                    <span class="inactive-badge">BANNED</span>
                <?php endif ?>
            </h1>
            <?php if ($user['bio']): ?>
                <div class="bio"><?= nl2br(htmlspecialchars($user['bio'])) ?></div>
            <?php endif ?>
            <div class="stats">
                <?php foreach ([
                    'Archives'        => count($archives),
                    'Reviews'         => count($reviews),
                    'Total Downloads' => number_format($total_downloads),
                ] as $label => $val): ?>
                <div class="stat-item">
                    <strong><?= $val ?></strong>
                    <span><?= $label ?></span>
                </div>
                <?php endforeach ?>
            </div>
            <div class="member-since">
                Member since <?= date('M Y', strtotime($user['created_at'])) ?>
            </div>
        </div>
    </div>

    <div class="tabs">
        <button class="tab active"  onclick="showTab('archives', this)">Archives (<?= count($archives) ?>)</button>
        <button class="tab"         onclick="showTab('reviews',  this)">Reviews (<?= count($reviews) ?>)</button>
    </div>

    <div id="archives-tab" class="tab-content active">
        <?php if (empty($archives)): ?>
            <div class="no-content"><p>No archives submitted yet</p></div>
        <?php else: foreach ($archives as $a): ?>
            <div class="archive-item">
                <a href="/archive-project/archive/<?= $a['id'] ?>" class="archive-url">
                    <?= htmlspecialchars($a['url']) ?>
                </a>
                <div class="archive-meta">
                    <?= date('M d, Y', strtotime($a['submitted_at'])) ?> ·
                    <?= number_format($a['download_count']) ?> downloads ·
                    <?= ucfirst($a['status']) ?>
                </div>
            </div>
        <?php endforeach; endif ?>
    </div>

    <div id="reviews-tab" class="tab-content">
        <?php if (empty($reviews)): ?>
            <div class="no-content"><p>No reviews written yet</p></div>
        <?php else: foreach ($reviews as $r): ?>
            <div class="review-item">
                <a href="/archive-project/archive/<?= $r['archive_id'] ?>" class="archive-url">
                    <?= htmlspecialchars($r['url']) ?>
                </a>
                <div class="rating">
                    <?= str_repeat('★', $r['rating']) . str_repeat('☆', 5 - $r['rating']) ?>
                </div>
                <?php if ($r['comment']): ?>
                    <div><?= nl2br(htmlspecialchars($r['comment'])) ?></div>
                <?php endif ?>
                <div class="archive-meta"><?= date('M d, Y', strtotime($r['reviewed_at'])) ?></div>
            </div>
        <?php endforeach; endif ?>
    </div>
</div>

<script>
function showTab(name, btn) {
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab').forEach(b => b.classList.remove('active'));
    document.getElementById(name + '-tab').classList.add('active');
    btn.classList.add('active');
}
</script>
</body>
</html>