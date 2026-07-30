<?php
session_start();
require_once('actions/dbh.php');

$userId = $_SESSION['user_id'] ?? null;
$username = htmlspecialchars($_SESSION['username'] ?? 'Guest');
$userEmail = '';

$navPhotos = [];
$albums = [];
$feedPhotos = [];

try {
    $navStmt = $conn->prepare('SELECT id, title, thumbnail, url FROM photos WHERE is_public = 1 ORDER BY uploaded_at DESC LIMIT 4');
    $navStmt->execute();
    $navPhotos = $navStmt->fetchAll(PDO::FETCH_ASSOC);

    $albumsStmt = $conn->prepare(
        'SELECT a.id, a.title, a.album_description, COUNT(p.id) AS photo_count, MAX(p.uploaded_at) AS last_upload
         FROM album a
         LEFT JOIN photos p ON p.album_id = a.id
         WHERE a.user_id = ?
         GROUP BY a.id
         ORDER BY last_upload DESC
         LIMIT 6'
    );
    $albumsStmt->execute([$userId]);
    $albums = $albumsStmt->fetchAll(PDO::FETCH_ASSOC);

    if ($userId) {
        $userStmt = $conn->prepare('SELECT email FROM users WHERE id = ?');
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);
        $userEmail = $user['email'] ?? '';
    }

    $feedStmt = $conn->prepare(
        'SELECT p.id, p.title, p.photo_description, p.thumbnail, p.url, p.uploaded_at, a.title AS album_title, u.username AS owner,
            COALESCE(l.likes_count, 0) AS likes_count,
            CASE WHEN ul.user_id IS NULL THEN 0 ELSE 1 END AS liked
         FROM photos p
         JOIN album a ON p.album_id = a.id
         JOIN users u ON p.user_id = u.id
         LEFT JOIN (
             SELECT photo_id, COUNT(*) AS likes_count
             FROM photo_likes
             GROUP BY photo_id
         ) l ON l.photo_id = p.id
         LEFT JOIN photo_likes ul ON ul.photo_id = p.id AND ul.user_id = ?
         WHERE p.is_public = 1 OR p.user_id = ?
         ORDER BY p.uploaded_at DESC
         LIMIT 12'
    );
    $feedStmt->execute([$userId, $userId]);
    $feedPhotos = $feedStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Keep arrays empty if the schema is not fully installed yet.
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Great+Vibes&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/dashboard.css">
    <title>Dashboard</title>
</head>

<body>
    <section class="page">
        <nav>
            <div class="nav-brand">
                <div class="logo">
                    <img src="photo-camera.png" alt="logo">
                    <div class="logo-text">
                        <span>Nicole's</span>
                        <strong>GALLERY</strong>
                    </div>
                </div>
            </div>
            <button class="burger-btn" aria-label="Open menu">
                <span></span>
                <span></span>
                <span></span>
            </button>
            <ul class="menu">
                <li><a href="#home">HOME</a></li>
                <li><a href="#photos">PHOTOS</a></li>
                <li><a href="#albums">ALBUM</a></li>
                <?php if ($userId): ?>
                    <li><a href="#" id="settingsToggle">SETTINGS</a></li>
                    <li><a href="actions/logout.php">LOGOUT</a></li>
                <?php else: ?>
                    <li><a href="login.html">LOGIN</a></li>
                    <li><a href="sign_up.html">SIGN UP</a></li>
                <?php endif; ?>
            </ul>
        </nav>

        <section class="hero" id="home">
            <div class="hero-copy">
                <p class="eyebrow">Welcome back, <?php echo $username; ?></p>
                <h1>Discover and share your favorite moments.</h1>
                <p class="subtitle">Browse the latest photo uploads, manage your albums, search across content, and like the gallery favorites.</p>
            </div>
            <div class="hero-actions">
                <span class="hero-badge"><?php echo count($feedPhotos); ?> photos visible</span>
                <span class="hero-badge"><?php echo count($albums); ?> albums</span>
            </div>
        </section>

        <section class="search-section">
            <form id="searchform" class="search-form">
                <input type="text" name="search" placeholder="Search photos, album names or upload dates" required>
                <button type="submit" id="search">Search</button>
            </form>
            <div id="searchMessage" class="form-message"></div>
            <div id="searchResults" class="search-results"></div>
        </section>

        <section class="dashboard-grid">
            <aside class="sidebar-panel">
                <?php if ($userId): ?>
                    <div class="panel card upload-panel">
                        <h2>Add a new media item</h2>
                        <form id="addPhotoForm" enctype="multipart/form-data">
                            <label>
                                Title
                                <input type="text" name="title" required>
                            </label>
                            <label>
                                Description
                                <textarea name="photo_description" rows="3"></textarea>
                            </label>
                            <label>
                                Album title
                                <input type="text" name="album_title" value="Portfolio" required>
                            </label>
                            <label>
                                Upload photo or video
                                <input type="file" name="photo_file" accept="image/png,image/jpeg,image/jpg,image/gif,image/webp,video/*">
                            </label>
                            <p class="field-note">Max video size is 8MB. Or provide a URL if you want to link a remote image or video file.</p>
                            <label>
                                Media URL
                                <input type="url" name="url" placeholder="https://example.com/media.mp4">
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="is_public" checked>
                                Share publicly
                            </label>
                            <button type="submit">Post photo</button>
                        </form>
                        <div id="addPhotoMessage" class="form-message"></div>
                    </div>

                    <div class="panel card album-panel" id="albums">
                        <h2>Your albums</h2>
                        <?php if (!empty($albums)): ?>
                            <div class="album-list">
                                <?php foreach ($albums as $album): ?>
                                    <article class="album-card">
                                        <h3><?php echo htmlspecialchars($album['title']); ?></h3>
                                        <p><?php echo htmlspecialchars($album['album_description'] ?: 'No description yet'); ?></p>
                                        <div class="album-meta">
                                            <span><?php echo (int)$album['photo_count']; ?> photos</span>
                                            <span><?php echo $album['last_upload'] ? date('M j, Y', strtotime($album['last_upload'])) : 'Empty'; ?></span>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="empty-state">No albums created yet. Add a photo to start your first album.</p>
                        <?php endif; ?>
                    </div>

                <?php else: ?>
                    <div class="panel card guest-panel">
                        <h2>Public gallery</h2>
                        <p>Browse public photos without logging in. Create an account to post your own gallery images, like favorites, and manage albums.</p>
                        <div class="guest-actions">
                            <a class="button-outline" href="login.html">Login</a>
                            <a class="button-outline" href="sign_up.html">Sign Up</a>
                        </div>
                    </div>
                <?php endif; ?>
            </aside>

            <main class="content-panel">
                <section class="photo-feed" id="photos">
                    <div class="section-header">
                        <h2>Photo feed</h2>
                        <p class="subtitle">Latest posts from your gallery and public uploads.</p>
                    </div>
                    <?php if (!empty($feedPhotos)): ?>
                        <div id="photosGrid" class="photos-grid">
                            <?php foreach ($feedPhotos as $photo): ?>
                                <article class="photo-card" id="photo-<?php echo $photo['id']; ?>">
                                    <div class="photo-frame">
                                        <?php $mediaUrl = htmlspecialchars($photo['thumbnail']); ?>
                                        <?php if (preg_match('/\.(mp4|webm|ogg|mov|m4v)(\?|$)/i', $mediaUrl)): ?>
                                            <video controls preload="metadata" title="<?php echo htmlspecialchars($photo['title']); ?>">
                                                <source src="<?php echo $mediaUrl; ?>" type="video/<?php echo strtolower(pathinfo($mediaUrl, PATHINFO_EXTENSION)); ?>">
                                                Your browser does not support the video tag.
                                            </video>
                                        <?php else: ?>
                                            <img src="<?php echo $mediaUrl; ?>" alt="<?php echo htmlspecialchars($photo['title']); ?>">
                                        <?php endif; ?>
                                    </div>
                                    <div class="photo-details">
                                        <div class="photo-header">
                                            <div>
                                                <h3><?php echo htmlspecialchars($photo['title']); ?></h3>
                                                <p class="photo-subtitle"><?php echo htmlspecialchars($photo['album_title']); ?> • <?php echo htmlspecialchars($photo['owner']); ?></p>
                                            </div>
                                            <button class="like-btn<?php echo $photo['liked'] ? ' liked' : ''; ?>" data-like-photo-id="<?php echo $photo['id']; ?>">
                                                <?php echo $photo['liked'] ? '♥' : '♡'; ?> <span class="likes-count"><?php echo (int)$photo['likes_count']; ?></span>
                                            </button>
                                        </div>
                                        <p><?php echo htmlspecialchars($photo['photo_description'] ?: 'No description provided.'); ?></p>
                                        <div class="photo-meta-row">
                                            <span><?php echo date('M j, Y', strtotime($photo['uploaded_at'])); ?></span>
                                            <?php if ($photo['owner'] === $username): ?>
                                                <span class="tag">Your photo</span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($photo['owner'] === $username): ?>
                                            <div class="photo-actions">
                                                <button type="button" class="action-btn edit-btn" data-photo-action="edit" data-photo-id="<?php echo $photo['id']; ?>">Edit</button>
                                                <button type="button" class="action-btn delete-btn" data-photo-action="delete" data-photo-id="<?php echo $photo['id']; ?>">Delete</button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="empty-state">No photos yet. Post your first image and share it with the gallery.</p>
                    <?php endif; ?>
                </section>
            </main>
        </section>
    </section>

    <div class="modal-overlay hidden" id="settingsModal" role="dialog" aria-modal="true" aria-labelledby="settingsTitle">
        <div class="modal-panel hidden">
            <button type="button" class="modal-close" id="settingsClose" aria-label="Close settings">×</button>
            <h2 id="settingsTitle">Account settings</h2>
            <form id="settingsForm">
                <label>
                    Username
                    <input type="text" name="username" value="<?php echo htmlspecialchars($username); ?>" required>
                </label>
                <label>
                    Email
                    <input type="email" name="email" value="<?php echo htmlspecialchars($userEmail); ?>" required>
                </label>
                <label>
                    New key
                    <input type="password" name="new_password" placeholder="Leave blank to keep current">
                </label>
                <label>
                    Confirm key
                    <input type="password" name="confirm_password" placeholder="Leave blank to keep current">
                </label>
                <label>
                    Current key
                    <input type="password" name="current_password" placeholder="Enter current key to save changes" required>
                </label>
                <button type="submit">Save settings</button>
            </form>
            <div id="settingsMessage" class="form-message"></div>
            <button type="button" id="deleteAccountBtn" class="delete-account-btn">Delete account</button>
        </div>
    </div>
    <script src="actions/ajax.js"></script>
</body>

</html>
