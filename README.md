# Archive Project – Code Documentation

## Table of Contents
1. [Middleware](#middleware)
2. [File Upload](#file-upload)
3. [Authentication Routes](#authentication-routes)
4. [Archive Routes](#archive-routes)
5. [Review Routes](#review-routes)
6. [User & Profile Routes](#user--profile-routes)
7. [Admin Routes](#admin-routes)
8. [Admin Panel Actions](#admin-panel-actions)
9. [Views Summary](#views-summary)

---

## Middleware

Middleware functions run before route handlers to enforce access control. They are called at the top of any route that needs to be protected.

### `require_login(): void`

Ensures the user is authenticated before accessing a page. If no session exists, or the session doesn't contain a `user_id`, execution is halted immediately.

```php
function require_login(): void
{
    if (session_status() === PHP_SESSION_NONE)
        session_start();
    if (!isset($_SESSION['user_id'])) {
        echo '<p>You must be <a href="/archive-project/login">logged in</a> to view this page.</p>';
        exit;
    }
}
```

- `session_status() === PHP_SESSION_NONE` checks whether a session has already been started — if not, it starts one. This prevents "session already active" warnings.
- `!isset($_SESSION['user_id'])` checks whether the session holds a logged-in user. If the key is missing, the user is not authenticated.
- `exit` is called after the message to stop any further code in the route from running.

---

### `require_admin(PDO $pdo): void`

Extends `require_login()` to also check that the logged-in user holds the `admin` role. Non-admins receive a `403 Forbidden` response.

```php
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
```

- `require_login()` is called first, if the user isn't logged in, execution stops, before the db is touched.
- The role is fetched fresh from the database on every request rather than trusting `$_SESSION['role']`, which could be outdated if the role was changed by an admin.
- `http_response_code(403)` sets the correct HTTP status so browsers and crawlers understand the page is forbidden, not just empty.

---

## File Upload

### `handle_torrent_upload(array $file, string $back_url): array`

Validates and saves an uploaded `.torrent` file. Returns `[relative_path, file_size]` on success, or halts with an error message on failure. The `$back_url` parameter is used in error links so the user can retry.

**Extension and size validation:**

```php
if (pathinfo($file['name'], PATHINFO_EXTENSION) !== 'torrent') {
    echo '<p>Only .torrent files allowed. <a href="' . $back_url . '">Try again</a></p>';
    exit;
}
if ($file['size'] > 2 * 1024 * 1024) {
    echo '<p>File too large (max 2 MB). <a href="' . $back_url . '">Try again</a></p>';
    exit;
}
```

- `pathinfo($file['name'], PATHINFO_EXTENSION)` extracts the file extension from the original filename. This prevents users from uploading other file types by renaming them.
- `2 * 1024 * 1024` evaluates to 2,097,152 bytes (2 MB). Torrent files are metadata only, so 2 MB is a generous limit.

**Saving the file to disk:**

```php
$dir = __DIR__ . '/uploads/torrents/';
if (!is_dir($dir))
    mkdir($dir, 0755, true);

$filename = uniqid('torrent_') . '.torrent';
if (!move_uploaded_file($file['tmp_name'], $dir . $filename)) {
    echo '<p>Upload failed. <a href="' . $back_url . '">Try again</a></p>';
    exit;
}

return ['/uploads/torrents/' . $filename, $file['size']];
```

- `__DIR__`  absolute path to directory containing current file, making the upload path absolute no matter where PHP is called from.
- `mkdir($dir, 0755, true)` creates the dir with standard read/execute permissions. The `true` flag creates any missing parent directories too.
- `uniqid('torrent_')` generates a unique filename based on the current timestamp in ms, prefixed with `torrent_`. This prevents filename collisions when multiple users upload files simultaneously.
- `move_uploaded_file()` is the safe PHP way to move an uploaded file — it verifies the file came from an HTTP upload before moving it, which protects against path traversal attacks.
- The function returns a relative path (starting with `/uploads/...`) suitable for storing in the database and serving over HTTP, alongside the raw file size in bytes.

---

## Authentication Routes

### `POST /login`

Handles login form submission. Looks up the user by username, verifies their password, and checks if their account is active before creating a session.

```php
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
```

- `trim($_POST['username'])` strips whitespace from the submitted username to avoid mismatches caused by spaces.
- `password_verify()` compares the plain-text submission against the stored bcrypt hash. Importantly, the error message is **identical** whether the username doesn't exist or the password is wrong — this prevents attackers from using the error to find valid usernames.
- The `is_active` check happens after password verification. Banned users still go through the full auth flow before being rejected, which avoids leaking which accounts exist.
- Three values are stored in `$_SESSION`: `user_id` (used for db queries), `username` (used for display), and `role` (used for quick UI checks like showing the admin link).

---

### `POST /register`

Handles new user registration with multiple layers of validation before inserting the user.

**Input validation:**

```php
$errors = [];

if (strlen($username) < 3)
    $errors[] = 'Username must be at least 3 characters';
if (!filter_var($email, FILTER_VALIDATE_EMAIL))
    $errors[] = 'Invalid email address';
if (strlen($password) < 8)
    $errors[] = 'Password must be at least 8 characters';
if ($password !== $_POST['password_confirm'])
    $errors[] = 'Passwords do not match';
```

All errors are put into an array before being displayed, so the user sees every problem at once rather than fixing them one at a time.

**Duplicate check:**

```php
foreach (['username' => $username, 'email' => $email] as $col => $val) {
    $chk = $pdo->prepare("SELECT id FROM users WHERE $col = ?");
    $chk->execute([$val]);
    if ($chk->fetch())
        $errors[] = ucfirst($col) . ' already in use';
}
```

- The loop checks both `username` and `email` for uniqueness in one block of code. The column name comes from the array key, not user input, so there is no SQL injection risk.
- `ucfirst($col)` capitalises the column name for a readable error message, e.g. `"Username already in use"`.

**Creating the user:**

```php
$pdo->prepare("INSERT INTO users (username, email, password_hash, role) VALUES (?,?,?,'user')")
    ->execute([$username, $email, password_hash($password, PASSWORD_BCRYPT)]);

$_SESSION['user_id'] = $pdo->lastInsertId();
```

- `password_hash($password, PASSWORD_BCRYPT)` hashes the password before it ever touches the database. The plain-text password is never stored.
- `$pdo->lastInsertId()` retrieves the auto-generated primary key of the newly inserted user, which is immediately written to the session so the user is logged in straight after registering.

---

### `GET /logout`

Destroys the session and redirects to the public archives page.

```php
get('/logout', function () {
    session_start();
    session_destroy();
    header('Location: /archive-project/archives');
    exit;
});
```

- `session_destroy()` destroys session
- `exit` is called after the redirect header to ensure no further code runs while the browser is redirecting.

---

## Archive Routes

### `GET /archives`

Lists all archives with pagination and optional URL search.

**Search and pagination query:**

```php
$page     = max(1, (int) ($_GET['page'] ?? 1));
$per_page = 20;
$offset   = ($page - 1) * $per_page;
$search   = trim($_GET['search'] ?? '');

if ($search) {
    $stmt = $pdo->prepare("
        SELECT a.id, a.url, a.submitted_at, a.status, a.download_count,
               a.torrent_path, u.username
        FROM archives a LEFT JOIN users u ON a.user_id = u.id
        WHERE a.url ILIKE :s
        ORDER BY a.submitted_at DESC LIMIT :lim OFFSET :off
    ");
    $stmt->execute([':s' => "%$search%", ':lim' => $per_page, ':off' => $offset]);
}
```

- `max(1, (int) ($_GET['page'] ?? 1))` converts the page query parameter to int and ensures it never goes below 1, even if the URL is manually changed.
- `ILIKE` is PostgreSQL's case-insensitive `LIKE`. Wrapping `$search` in `%...%` matches the search term anywhere within the URL string.
- Named parameters (`:s`, `:lim`, `:off`) are used here because PDO does not allow mixing named and positional `?` parameters in a single query.

**Calculating total pages:**

```php
$total       = $count->fetchColumn();
$total_pages = (int) ceil($total / $per_page);
```

- `fetchColumn()` returns just the first column of the first row — here, the `COUNT(*)` result.
- `ceil()` rounds up so that a remainder of results always gets its own page (e.g. 21 results at 20-per-page gives 2 pages, not 1).

---

### `GET /archive/$id`

Fetches a single archive with its submitter's profile and all associated reviews.

```php
$stmt = $pdo->prepare("
    SELECT a.*, u.username, u.profile_pic_path, u.bio
    FROM archives a LEFT JOIN users u ON a.user_id = u.id
    WHERE a.id = ?
");
$stmt->execute([$id]);
$archive = $stmt->fetch();
```

- `a.*` fetches every column from the archives table in one query, joined with selected profile fields from the submitting user.
- `LEFT JOIN` is used so that if the submitting user has been deleted, the archive row is still returned (with `null` user fields) rather than disappearing from the site entirely.

**Average rating calculation:**

```php
$avg_rating = $reviews
    ? array_sum(array_column($reviews, 'rating')) / count($reviews)
    : 0;
```

- `array_column($reviews, 'rating')` extracts just the `rating` values from all review rows into a flat array, which `array_sum()` then totals.
- The ternary guard prevents a division-by-zero error when no reviews.

---

### `POST /submit`

Handles new archive submission. Accepts a URL, a torrent file, or both.

```php
$has_url     = $url !== '';
$has_torrent = !empty($_FILES['torrent']) && $_FILES['torrent']['error'] === UPLOAD_ERR_OK;

if (!$has_url && !$has_torrent) {
    echo '<p>Please provide a URL or torrent file. <a href="/archive-project/submit">Try again</a></p>';
    return;
}

[$torrent_path, $torrent_size] = $has_torrent
    ? handle_torrent_upload($_FILES['torrent'], '/archive-project/submit')
    : [null, null];

$pdo->prepare("
    INSERT INTO archives (url, user_id, status, description, torrent_path, torrent_file_size, archive_size)
    VALUES (?, ?, 'pending', ?, ?, ?, 0)
")->execute([$has_url ? $url : null, $_SESSION['user_id'], $description ?: null, $torrent_path, $torrent_size]);
```

- `$_FILES['torrent']['error'] === UPLOAD_ERR_OK` checks PHP's upload error code. A value of `0` means the file arrived without problems. Checking this prevents processing a failed or empty upload.
- The array destructuring `[$torrent_path, $torrent_size] = ...` unpacks the two values returned by `handle_torrent_upload()`.
- New archives are always inserted with `status = 'pending'` until admin approved.
- `$description ?: null` converts an empty string description to `null` so the database stores `NULL` instead of an empty string.

---

### `POST /edit-archive/$id`

Updates an existing archive. Verifies ownership before making any changes.

```php
$stmt = $pdo->prepare("SELECT id FROM archives WHERE id = ? AND user_id = ?");
$stmt->execute([$id, $_SESSION['user_id']]);
if (!$stmt->fetch()) {
    echo '<h1>Unauthorized</h1>';
    return;
}
```

The `AND user_id = ?` condition means the lookup only succeeds if the archive both exists **and** belongs to the logged-in user. This prevents users from editing non owned archives by guessing IDs in the URL.

**Replacing a torrent file:**

```php
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
```

- The old torrent file is fetched from the database before being replaced, then `unlink()` removes it from disk. Without this, old files would accumulate indefinitely.
- `file_exists()` is checked before `unlink()` to avoid a PHP warning if the file was already manually removed from the server.

---

### `GET /download/$filename`

Serves a torrent file for download and increments the archive's download counter.

```php
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
```

- The regex `^[a-zA-Z0-9_-]+\.torrent$` only allows alphanumeric characters, underscores, and hyphens before `.torrent`. This blocks path traversal attacks like `../../config.php`.
- `download_count = download_count + 1` is an atomic SQL increment, safe under concurrent downloads without race conditions.
- `Content-Disposition: attachment` tells the browser to download the file rather than attempt to render it.
- `readfile()` streams the file directly to the output buffer without loading it all into memory first, making it efficient for larger files.

---

### `POST /delete-archive/$id`

Deletes an archive, its torrent file from disk, and all associated reviews.

```php
if ($archive['torrent_path']) {
    $file = __DIR__ . $archive['torrent_path'];
    if (file_exists($file))
        unlink($file);
}
$pdo->prepare("DELETE FROM reviews  WHERE archive_id = ?")->execute([$id]);
$pdo->prepare("DELETE FROM archives WHERE id = ?")->execute([$id]);
```

Reviews are deleted before the archive to avoid a foreign key constraint violation — the `reviews` table references `archives`, so the parent record can only be removed after its children are gone.

---

## Review Routes

### `POST /review/$id`

Handles review submission. Updates an existing review if the user has already reviewed this archive, otherwise inserts a new one.

```php
$rating  = max(1, min(5, (int) ($_POST['rating'] ?? 0)));
$comment = substr(trim($_POST['comment'] ?? ''), 0, 1000);

$stmt = $pdo->prepare("SELECT id FROM reviews WHERE archive_id = ? AND user_id = ?");
$stmt->execute([$id, $_SESSION['user_id']]);
$existing = $stmt->fetchColumn();

if ($existing) {
    $pdo->prepare("UPDATE reviews SET rating=?, comment=?, reviewed_at=NOW() WHERE id=?")
        ->execute([$rating, $comment, $existing]);
} else {
    $pdo->prepare("INSERT INTO reviews (archive_id, user_id, rating, comment) VALUES (?,?,?,?)")
        ->execute([$id, $_SESSION['user_id'], $rating, $comment]);
}
```

- `max(1, min(5, ...))` clamps the rating server-side between 1 and 5 regardless of what the client sends. Never trust front-end validation alone.
- `substr(..., 0, 1000)` enforces the 1000-character comment limit at the server level.
---

## User & Profile Routes

### `GET /user/$username`

Fetches a full public profile for any user, including their archives, reviews, and total download count.

```php
$stmt = $pdo->prepare("
    SELECT r.*, a.url, a.id as archive_id FROM reviews r
    JOIN archives a ON r.archive_id = a.id
    WHERE r.user_id = ? ORDER BY r.reviewed_at DESC
");
$stmt->execute([$user['id']]);
$reviews = $stmt->fetchAll();

$total_downloads = array_sum(array_column($archives, 'download_count'));
```

- The reviews query uses an `INNER JOIN` (not `LEFT JOIN`) because a review must belong to an archive — if the archive was deleted, the orphaned review is excluded rather than showing a blank URL.
- `array_column($archives, 'download_count')` puts every download count into a flat array, and `array_sum()` totals them in a single PHP expression without needing an extra SQL query.

---

## Admin Routes

### `GET /admin` and `POST /admin`

Both routes are protected by `require_admin($pdo)` and both include the same view file, which handles its own POST logic internally.

```php
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
```

---

## Admin Panel Actions

All moderation actions are submitted via `POST` to `/admin` using hidden `action` and `target_id` fields, then sent through a `switch` statement.

```php
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
```

- `(int) ($_POST['target_id'] ?? 0)` casts the target ID to an integer immediately, neutralising any attempt to inject a non-numeric value.
- The `AND username != 'admin'` guard on `ban_user` and `demote_user`, and `AND role != 'admin'` on `delete_user`, are enforced **in the SQL query itself** — not just in the UI. This means the root admin account cannot be removed or demoted even if someone crafts a manual POST request bypassing the interface.
- After any action, `header('Location: ' . $_SERVER['REQUEST_URI'])` redirects back to the same admin page, implementing the Post/Redirect/Get pattern to prevent form resubmission on browser refresh.

**Get stats query:**

```php
$stats = $pdo->query("
    SELECT
        COUNT(*) FILTER (WHERE status='pending')  AS pending,
        COUNT(*) FILTER (WHERE status='approved') AS approved,
        COUNT(*) FILTER (WHERE status='rejected') AS rejected,
        COUNT(*)                                  AS total
    FROM archives
")->fetch();
```

`COUNT(*) FILTER (WHERE ...)` is a PostgreSQL aggregate filter. It counts all rows for `total` and simultaneously counts subsets for each status in a single query, avoiding four separate `SELECT COUNT(*)` calls.

---

## Views Summary

| View File | Purpose |
|---|---|
| `views/archives.php` | Public archive listing with search and pagination |
| `views/archiveid.php` | Single archive detail with reviews and download button |
| `views/my-archives.php` | Logged-in user's own archive list with edit/delete |
| `views/submit.php` | New archive submission form |
| `views/edit.php` | Archive edit form pre-filled with existing values |
| `views/review.php` | Star-rating review form with live character counter |
| `views/dashboard.php` | User dashboard with navigation cards |
| `views/users.php` | Public user profile with tabbed archives/reviews |
| `views/admin.php` | Admin panel with stats, archive moderation, and user management |