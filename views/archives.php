<?php if (session_status() === PHP_SESSION_NONE) session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Public Archives</title>
    <link rel="stylesheet" href="/archive-project/views/archives.css">
</head>
<body>
<div class="container">
    <header>
        <h1>Public Archive</h1>
        <div class="stats">
            <span><strong><?= number_format($total) ?></strong> archives preserved</span>
            <span>Page <?= $page ?> of <?= $total_pages ?></span>
        </div>
        <div class="header-links">
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="/archive-project/dashboard">Dashboard</a>
                <a href="/archive-project/logout">Logout</a>
            <?php else: ?>
                <a href="/archive-project/login">Login</a>
                <a href="/archive-project/register">Register</a>
            <?php endif ?>
        </div>
        <form method="GET" action="/archive-project/archives" class="search-bar">
            <input type="text" name="search" placeholder="Search by URL…" value="<?= htmlspecialchars($search) ?>">
            <button type="submit">Search</button>
            <?php if ($search): ?>
                <a href="/archive-project/archives">Clear</a>
            <?php endif ?>
        </form>
    </header>

    <div class="archive-list">
        <?php if (empty($archives)): ?>
            <div class="no-results"><p>No archives found.</p></div>
        <?php else: foreach ($archives as $a): ?>
            <div class="archive-item">
                <a href="/archive-project/archive/<?= $a['id'] ?>" class="archive-url">
                    <?= htmlspecialchars($a['url']) ?>
                </a>
                <div class="archive-meta">
                    <span>By:
                        <?php if ($a['username']): ?>
                            <a href="/archive-project/user/<?= urlencode($a['username']) ?>">
                                <?= htmlspecialchars($a['username']) ?>
                            </a>
                        <?php else: ?>
                            <strong>Anonymous</strong>
                        <?php endif ?>
                    </span>
                    <span><?= date('d M, Y', strtotime($a['submitted_at'])) ?></span>
                    <span>↓ <?= number_format($a['download_count']) ?> downloads</span>
                    <span class="status status-<?= $a['status'] ?>"><?= ucfirst($a['status']) ?></span>
                    <?php if ($a['torrent_path']): ?>
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <a href="/archive-project/download/<?= basename($a['torrent_path']) ?>" class="download-btn">
                                Download Torrent
                            </a>
                        <?php else: ?>
                            <a href="/archive-project/login" class="download-btn" style="background:#6c757d">
                                Login to Download
                            </a>
                        <?php endif ?>
                    <?php endif ?>
                </div>
            </div>
        <?php endforeach; endif ?>
    </div>

    <?php if ($total_pages > 1):
        $qs = $search ? '&search=' . urlencode($search) : '';
    ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?page=<?= $page - 1 ?><?= $qs ?>">← Previous</a>
        <?php endif ?>
        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
            <?php if ($i === $page): ?>
                <span class="current"><?= $i ?></span>
            <?php else: ?>
                <a href="?page=<?= $i ?><?= $qs ?>"><?= $i ?></a>
            <?php endif ?>
        <?php endfor ?>
        <?php if ($page < $total_pages): ?>
            <a href="?page=<?= $page + 1 ?><?= $qs ?>">Next →</a>
        <?php endif ?>
    </div>
    <?php endif ?>
</div>
</body>
</html>