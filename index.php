<?php
require_once 'config.php';
require_once 'router.php';

try {
    $pdo = new PDO(
        "pgsql:host=$host;port=$port;dbname=$dbname",
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    $GLOBALS['pdo'] = $pdo;
} catch(PDOException $e) {
    die("<p>Connection failed: " . $e->getMessage() . "</p>");
};


// Middleware

function require_login(): void
{
    if (session_status() === PHP_SESSION_NONE)
        session_start();
    if (!isset($_SESSION['user_id'])) {
        echo '<p>You must be <a href="/archive-project/login">logged in</a> to view this page.</p>';
        exit;
    }
}

function require_admin(PDO $pdo): void
{
    require_login();
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user || $user['role'] !== 'admin') {
        http_response_code(403);
        echo '<h1>Forbidden</h1>';
        exit;
    }
}

/**
 * Validates and saves an uploaded torrent file.
 * Returns [relative_path, size] or exits with an error message.
 */
function handle_torrent_upload($file, $back_url)
{
    if (pathinfo($file['name'], PATHINFO_EXTENSION) !== 'torrent') {
        echo '<p>Only .torrent files allowed. <a href="' . $back_url . '">Try again</a></p>';
        exit;
    }
    if ($file['size'] > 2 * 1024 * 1024) {
        echo '<p>File too large (max 2 MB). <a href="' . $back_url . '">Try again</a></p>';
        exit;
    }

    $dir = __DIR__ . '/uploads/torrents/';
    if (!is_dir($dir))
        mkdir($dir, 0755, true);

    $filename = uniqid('torrent_') . '.torrent';
    if (!move_uploaded_file($file['tmp_name'], $dir . $filename)) {
        echo '<p>Upload failed. <a href="' . $back_url . '">Try again</a></p>';
        exit;
    }

    return ['/uploads/torrents/' . $filename, $file['size']];
}

// Routes

get('/', fn() => include 'index.html');
get('/login', fn() => include 'login.html');
get('/register', fn() => include 'register.html');

get('/logout', function () {
    session_start();
    session_destroy();
    header('Location: /archive-project/archives');
    exit;
});

// Archives list

get('/archives', function () {
    global $pdo;
    if (session_status() === PHP_SESSION_NONE)
        session_start();

    $page = max(1, (int) ($_GET['page'] ?? 1));
    $per_page = 20;
    $offset = ($page - 1) * $per_page;
    $search = trim($_GET['search'] ?? '');

    if ($search) {
        $stmt = $pdo->prepare("
            SELECT a.id, a.url, a.submitted_at, a.status, a.download_count,
                   a.torrent_path, u.username
            FROM archives a LEFT JOIN users u ON a.user_id = u.id
            WHERE a.url ILIKE :s
            ORDER BY a.submitted_at DESC LIMIT :lim OFFSET :off
        ");
        $stmt->execute([':s' => "%$search%", ':lim' => $per_page, ':off' => $offset]);

        $count = $pdo->prepare("SELECT COUNT(*) FROM archives WHERE url ILIKE :s");
        $count->execute([':s' => "%$search%"]);
    } else {
        $stmt = $pdo->prepare("
            SELECT a.id, a.url, a.submitted_at, a.status, a.download_count,
                   a.torrent_path, u.username
            FROM archives a LEFT JOIN users u ON a.user_id = u.id
            ORDER BY a.submitted_at DESC LIMIT :lim OFFSET :off
        ");
        $stmt->execute([':lim' => $per_page, ':off' => $offset]);

        $count = $pdo->query("SELECT COUNT(*) FROM archives");
    }

    $archives = $stmt->fetchAll();
    $total = $count->fetchColumn();
    $total_pages = (int) ceil($total / $per_page);

    include 'views/archives.php';
});

// This archive

get('/archive/$id', function ($id) {
    global $pdo;
    if (session_status() === PHP_SESSION_NONE)
        session_start();

    if (!ctype_digit((string) $id)) {
        echo '<h1>Invalid Archive</h1>';
        return;
    }

    $stmt = $pdo->prepare("
        SELECT a.*, u.username, u.profile_pic_path, u.bio
        FROM archives a LEFT JOIN users u ON a.user_id = u.id
        WHERE a.id = ?
    ");
    $stmt->execute([$id]);
    $archive = $stmt->fetch();

    if (!$archive) {
        echo '<h1>Archive Not Found</h1><a href="/archive-project/archives">← Back</a>';
        return;
    }

    $stmt = $pdo->prepare("
        SELECT r.*, u.username FROM reviews r
        LEFT JOIN users u ON r.user_id = u.id
        WHERE r.archive_id = ? ORDER BY r.reviewed_at DESC
    ");
    $stmt->execute([$id]);
    $reviews = $stmt->fetchAll();
    $avg_rating = $reviews
        ? array_sum(array_column($reviews, 'rating')) / count($reviews)
        : 0;

    include 'views/archiveid.php';
});

// My archives

get('/my-archives', function () {
    global $pdo;
    require_login();

    $stmt = $pdo->prepare("
        SELECT id, url, description, submitted_at, status,
               download_count, torrent_path, torrent_file_size
        FROM archives WHERE user_id = ? ORDER BY submitted_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $archives = $stmt->fetchAll();

    include 'views/my-archives.php';
});

// Create 

get('/submit', function () {
    require_login();
    include 'views/submit.php';
});

post('/submit', function () {
    global $pdo;
    require_login();

    $url = trim($_POST['url'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $has_url = $url !== '';
    $has_torrent = !empty($_FILES['torrent']) && $_FILES['torrent']['error'] === UPLOAD_ERR_OK;

    if (!$has_url && !$has_torrent) {
        echo '<p>Please provide a URL or torrent file. <a href="/archive-project/submit">Try again</a></p>';
        return;
    }
    if ($has_url && !filter_var($url, FILTER_VALIDATE_URL)) {
        echo '<p>Invalid URL. <a href="/archive-project/submit">Try again</a></p>';
        return;
    }

    [$torrent_path, $torrent_size] = $has_torrent
        ? handle_torrent_upload($_FILES['torrent'], '/archive-project/submit')
        : [null, null];

    $pdo->prepare("
        INSERT INTO archives (url, user_id, status, description, torrent_path, torrent_file_size, archive_size)
        VALUES (?, ?, 'pending', ?, ?, ?, 0)
    ")->execute([$has_url ? $url : null, $_SESSION['user_id'], $description ?: null, $torrent_path, $torrent_size]);

    echo '<h1>Submitted!</h1>
          <p><a href="/archive-project/dashboard">Dashboard</a> &middot;
             <a href="/archive-project/submit">Submit another</a></p>';
});

// Edit

get('/edit-archive/$id', function ($id) {
    global $pdo;
    require_login();
    if (!ctype_digit((string) $id)) {
        echo '<h1>Invalid ID</h1>';
        return;
    }

    $stmt = $pdo->prepare("SELECT * FROM archives WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $_SESSION['user_id']]);
    $archive = $stmt->fetch();

    if (!$archive) {
        echo '<h1>Not Found</h1><a href="/archive-project/my-archives">← Back</a>';
        return;
    }
    include 'views/edit.php';
});

post('/edit-archive/$id', function ($id) {
    global $pdo;
    require_login();
    if (!ctype_digit((string) $id)) {
        echo '<h1>Invalid ID</h1>';
        return;
    }

    $stmt = $pdo->prepare("SELECT id FROM archives WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $_SESSION['user_id']]);
    if (!$stmt->fetch()) {
        echo '<h1>Unauthorized</h1>';
        return;
    }

    $url = trim($_POST['url']);
    $description = trim($_POST['description'] ?? '');

    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        echo '<p>Invalid URL. <a href="/archive-project/edit-archive/' . $id . '">Try again</a></p>';
        return;
    }

    $torrent_path = $torrent_size = null;
    if (!empty($_FILES['torrent']) && $_FILES['torrent']['error'] === UPLOAD_ERR_OK) {
        $old = $pdo->prepare("SELECT torrent_path FROM archives WHERE id = ?");
        $old->execute([$id]);
        $old_path = $old->fetchColumn();
        if ($old_path && file_exists(__DIR__ . $old_path))
            unlink(__DIR__ . $old_path);

        [$torrent_path, $torrent_size] = handle_torrent_upload(
            $_FILES['torrent'],
            '/archive-project/edit-archive/' . $id
        );
    }

    if ($torrent_path) {
        $pdo->prepare("UPDATE archives SET url=?, description=?, torrent_path=?, torrent_file_size=? WHERE id=?")
            ->execute([$url, $description, $torrent_path, $torrent_size, $id]);
    } else {
        $pdo->prepare("UPDATE archives SET url=?, description=? WHERE id=?")
            ->execute([$url, $description, $id]);
    }

    echo '<h1>Updated!</h1><a href="/archive-project/my-archives">← My Archives</a>';
});

// Delete

post('/delete-archive/$id', function ($id) {
    global $pdo;
    require_login();
    if (!ctype_digit((string) $id)) {
        http_response_code(400);
        echo 'Invalid ID';
        return;
    }

    $stmt = $pdo->prepare("SELECT * FROM archives WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $_SESSION['user_id']]);
    $archive = $stmt->fetch();

    if (!$archive) {
        http_response_code(404);
        echo 'Not found';
        return;
    }

    if ($archive['torrent_path']) {
        $file = __DIR__ . $archive['torrent_path'];
        if (file_exists($file))
            unlink($file);
    }
    $pdo->prepare("DELETE FROM reviews  WHERE archive_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM archives WHERE id = ?")->execute([$id]);
    echo 'Deleted';
});

// Download

get('/download/$filename', function ($filename) {
    global $pdo;
    require_login();

    if (!preg_match('/^[a-zA-Z0-9_-]+\.torrent$/', $filename))
        die('Invalid filename');

    $filepath = __DIR__ . '/uploads/torrents/' . $filename;
    if (!file_exists($filepath))
        die('File not found');

    $pdo->prepare("UPDATE archives SET download_count = download_count + 1 WHERE torrent_path = ?")
        ->execute(['/uploads/torrents/' . $filename]);

    header('Content-Type: application/x-bittorrent');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    readfile($filepath);
});

// Review

get('/review/$id', fn($id) => include 'views/review.php');

post('/review/$id', function ($id) {
    global $pdo;
    require_login();
    if (!ctype_digit((string) $id)) {
        echo '<h1>Invalid Archive</h1>';
        return;
    }

    $rating = max(1, min(5, (int) ($_POST['rating'] ?? 0)));
    $comment = substr(trim($_POST['comment'] ?? ''), 0, 1000);

    $stmt = $pdo->prepare("SELECT id FROM reviews WHERE archive_id = ? AND user_id = ?");
    $stmt->execute([$id, $_SESSION['user_id']]);
    $existing = $stmt->fetchColumn();

    if ($existing) {
        $pdo->prepare("UPDATE reviews SET rating=?, comment=?, reviewed_at=NOW() WHERE id=?")
            ->execute([$rating, $comment, $existing]);
        $msg = 'Review updated!';
    } else {
        $pdo->prepare("INSERT INTO reviews (archive_id, user_id, rating, comment) VALUES (?,?,?,?)")
            ->execute([$id, $_SESSION['user_id'], $rating, $comment]);
        $msg = 'Review submitted!';
    }

    echo '<p>' . $msg . ' <a href="/archive-project/archive/' . $id . '">← Back to archive</a></p>';
});

// Register + Login 

post('/register', function () {
    global $pdo;
    if (session_status() === PHP_SESSION_NONE)
        session_start();

    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $errors = [];

    if (strlen($username) < 3)
        $errors[] = 'Username must be at least 3 characters';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors[] = 'Invalid email address';
    if (strlen($password) < 8)
        $errors[] = 'Password must be at least 8 characters';
    if ($password !== $_POST['password_confirm'])
        $errors[] = 'Passwords do not match';

    foreach (['username' => $username, 'email' => $email] as $col => $val) {
        $chk = $pdo->prepare("SELECT id FROM users WHERE $col = ?");
        $chk->execute([$val]);
        if ($chk->fetch())
            $errors[] = ucfirst($col) . ' already in use';
    }

    if ($errors) {
        foreach ($errors as $e)
            echo '<p>' . htmlspecialchars($e) . '</p>';
        echo '<a href="/archive-project/register">Try again</a>';
        return;
    }

    $pdo->prepare("INSERT INTO users (username, email, password_hash, role) VALUES (?,?,?,'user')")
        ->execute([$username, $email, password_hash($password, PASSWORD_BCRYPT)]);

    $_SESSION['user_id'] = $pdo->lastInsertId();
    $_SESSION['username'] = $username;
    $_SESSION['role'] = 'user';

    header('Location: /archive-project/dashboard');
    exit;
});

post('/login', function () {
    global $pdo;
    if (session_status() === PHP_SESSION_NONE)
        session_start();

    $stmt = $pdo->prepare("SELECT id, username, password_hash, role, is_active FROM users WHERE username = ?");
    $stmt->execute([trim($_POST['username'])]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($_POST['password'], $user['password_hash'])) {
        echo '<p>Invalid username or password. <a href="/archive-project/login">Try again</a></p>';
        return;
    }
    if (!$user['is_active']) {
        echo '<p>Your account has been disabled.</p>';
        return;
    }

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];

    header('Location: /archive-project/dashboard');
    exit;
});

// Dashboard + User profile 

get('/dashboard', function () {
    require_login();
    include 'views/dashboard.php';
});

get('/user/$username', function ($username) {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT id, username, email, profile_pic_path, bio, role, created_at, is_active
        FROM users WHERE username = ?
    ");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user) {
        echo '<h1>User Not Found</h1><a href="/archive-project/archives">← Back</a>';
        return;
    }

    $stmt = $pdo->prepare("
        SELECT id, url, submitted_at, status, download_count, torrent_path
        FROM archives WHERE user_id = ? ORDER BY submitted_at DESC
    ");
    $stmt->execute([$user['id']]);
    $archives = $stmt->fetchAll();

    $stmt = $pdo->prepare("
        SELECT r.*, a.url, a.id as archive_id FROM reviews r
        JOIN archives a ON r.archive_id = a.id
        WHERE r.user_id = ? ORDER BY r.reviewed_at DESC
    ");
    $stmt->execute([$user['id']]);
    $reviews = $stmt->fetchAll();

    $total_downloads = array_sum(array_column($archives, 'download_count'));

    include 'views/users.php';
});

// Admin 

get('/admin', function () {
    global $pdo;
    require_admin($pdo);
    include 'views/admin.php';
});
post('/admin', function () {
    global $pdo;
    require_admin($pdo);
    include 'views/admin.php';
});

