<?php
global $pdo;
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    echo '<p>You must be <a href="/archive-project/login">logged in</a> to write reviews.</p>';
    return;
}
if (!ctype_digit((string) $id)) { echo '<h1>Invalid Archive</h1>'; return; }

$stmt = $pdo->prepare("SELECT id, url FROM archives WHERE id = ?");
$stmt->execute([$id]);
$archive = $stmt->fetch();
if (!$archive) { echo '<h1>Archive Not Found</h1>'; return; }

$stmt = $pdo->prepare("SELECT id FROM reviews WHERE archive_id = ? AND user_id = ?");
$stmt->execute([$id, $_SESSION['user_id']]);
$already_reviewed = (bool) $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Write Review</title>
    <link rel="stylesheet" href="/archive-project/views/review.css">
</head>
<body>
<div class="container">
    <a href="/archive-project/archive/<?= $id ?>" class="back-link">← Back to Archive</a>

    <div class="review-card">
        <h1>Write a Review</h1>

        <div class="archive-preview">
            <strong>Reviewing:</strong><br>
            <?= htmlspecialchars($archive['url']) ?>
        </div>

        <?php if ($already_reviewed): ?>
            <div class="warning">
                <strong>Note:</strong> You've already reviewed this archive — submitting will update it.
            </div>
        <?php endif ?>

        <form method="POST" action="/archive-project/review/<?= $id ?>" id="reviewForm">
            <label>Rating *</label>
            <div class="rating-input" id="ratingInput">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <button type="button" class="star-btn" data-rating="<?= $i ?>">★</button>
                <?php endfor ?>
            </div>
            <input type="hidden" name="rating" id="ratingValue" required>

            <label>Comment <em>(optional)</em></label>
            <textarea name="comment" id="comment" placeholder="Share your thoughts…"></textarea>
            <div class="char-count"><span id="charCount">0</span> / 1000</div>

            <button type="submit">Submit Review</button>
        </form>
    </div>
</div>

<script>
const stars      = document.querySelectorAll('.star-btn');
const ratingVal  = document.getElementById('ratingValue');
const charCount  = document.getElementById('charCount');
const comment    = document.getElementById('comment');
let selected     = 0;

function paintStars(n) {
    stars.forEach((s, i) => s.style.color = i < n ? '#ffc107' : '#ddd');
}

stars.forEach(s => {
    s.addEventListener('click', () => {
        selected = +s.dataset.rating;
        ratingVal.value = selected;
        paintStars(selected);
    });
    s.addEventListener('mouseenter', () => paintStars(+s.dataset.rating));
});
document.getElementById('ratingInput').addEventListener('mouseleave', () => paintStars(selected));

comment.addEventListener('input', () => {
    if (comment.value.length > 1000) comment.value = comment.value.slice(0, 1000);
    charCount.textContent = comment.value.length;
});

document.getElementById('reviewForm').addEventListener('submit', e => {
    if (!ratingVal.value) { e.preventDefault(); alert('Please select a rating'); }
});
</script>
</body>
</html>