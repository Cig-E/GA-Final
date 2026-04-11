<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Submit Archive</title>
    <link rel="stylesheet" href="/archive-project/views/submit.css">
</head>
<body>
<div class="container">
    <a href="/archive-project/dashboard" class="back-link">← Back to Dashboard</a>
    <h1>Submit Archive</h1>
    <p>Logged in as <strong><?= htmlspecialchars($_SESSION['username']) ?></strong></p>

    <form method="POST" action="/archive-project/submit" enctype="multipart/form-data">
        <label>URL to Archive <em>(optional)</em></label>
        <input type="url" name="url" placeholder="https://example.com/article">

        <label>Torrent File <em>(optional)</em></label>
        <input type="file" name="torrent" accept=".torrent">
        <p class="info">Max 2 MB · .torrent only · at least one field required</p>

        <label>Description <em>(optional)</em></label>
        <textarea name="description" placeholder="What is this archive about?"></textarea>

        <button type="submit">Submit Archive</button>
    </form>
</div>
</body>
</html>