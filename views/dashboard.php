<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard</title>
    <link rel="stylesheet" href="/archive-project/views/dashboard.css">
</head>
<body>
<div class="container">
    <div class="header">
        <h1>Dashboard</h1>
        <div class="user-info">
            Welcome back, <strong><?= htmlspecialchars($_SESSION['username']) ?></strong>
            <span class="role-badge role-<?= $_SESSION['role'] ?>"><?= strtoupper($_SESSION['role']) ?></span>
        </div>
    </div>

    <div class="dashboard-grid">
        <a href="/archive-project/submit"      class="dashboard-card card-submit">
            <div class="card-title">Submit Archive</div>
            <div class="card-description">Add new content to preserve</div>
        </a>
        <a href="/archive-project/my-archives" class="dashboard-card card-my-archives">
            <div class="card-title">My Archives</div>
            <div class="card-description">Manage your submissions</div>
        </a>
        <a href="/archive-project/archives"    class="dashboard-card card-browse">
            <div class="card-title">Browse Archives</div>
            <div class="card-description">Explore all preserved content</div>
        </a>
        <?php if ($_SESSION['role'] === 'admin'): ?>
        <a href="/archive-project/admin"       class="dashboard-card card-admin">
            <div class="card-title">Admin Panel</div>
            <div class="card-description">Manage users and content</div>
        </a>
        <?php endif ?>
    </div>

    <div class="logout-section">
        <a href="/archive-project/logout" class="logout-btn">Logout</a>
    </div>
</div>
</body>
</html>