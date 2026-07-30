<?php
session_start();
require_once('dbh.php');
header('Content-Type: application/json');
$action = $_POST['action'] ?? null;

switch ($action) {
    case 'signup':
        signup($conn);
        break;
    case 'login':
        login($conn);
        break;
    case 'search':
        searchPhotos($conn);
        break;
    case 'add_photo':
        addPhoto($conn);
        break;
    case 'toggle_like':
        toggleLike($conn);
        break;
    case 'update_settings':
        updateSettings($conn);
        break;
    case 'update_photo':
        updatePhoto($conn);
        break;
    case 'delete_photo':
        deletePhoto($conn);
        break;
    default:
        sendJson(['success' => false, 'message' => 'Invalid action']);
}

function signup($conn) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJson(['success' => false, 'message' => 'Wrong method']);
    }

    $username = htmlspecialchars(trim($_POST['username'] ?? ''));
    $email = htmlspecialchars(trim($_POST['email'] ?? ''));
    $pwd = trim($_POST['password'] ?? '');
    $confirm_pwd = trim($_POST['confirm_password'] ?? '');

    if (empty($username) || empty($email) || empty($pwd) || empty($confirm_pwd)) {
        sendJson(['success' => false, 'message' => 'All fields are required']);
    }

    if ($pwd !== $confirm_pwd) {
        sendJson(['success' => false, 'message' => 'Passwords do not match']);
    }

    try {
        $stmt = $conn->prepare('INSERT INTO users (username, email, pwd) VALUES (?, ?, ?)');
        $stmt->execute([$username, $email, password_hash($pwd, PASSWORD_DEFAULT)]);
        sendJson(['success' => true, 'message' => 'Signed up successfully']);
    } catch (PDOException $e) {
        $errorMessage = strpos($e->getMessage(), '23000') !== false
            ? 'Username or email already exists'
            : 'Database error';
        sendJson(['success' => false, 'message' => $errorMessage]);
    }
}

function login($conn) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJson(['success' => false, 'message' => 'Wrong method']);
    }

    $username = htmlspecialchars(trim($_POST['username'] ?? ''));
    $pwd = trim($_POST['password'] ?? '');

    if (empty($username) || empty($pwd)) {
        sendJson(['success' => false, 'message' => 'Username and key are required']);
    }

    $stmt = $conn->prepare('SELECT * FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        sendJson(['success' => false, 'message' => 'User does not exist']);
    }

    if (!password_verify($pwd, $user['pwd'])) {
        sendJson(['success' => false, 'message' => 'Wrong Key']);
    }

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    sendJson(['success' => true, 'message' => 'Welcome ' . $user['username']]);
}

function searchPhotos($conn) {
    $query = trim($_POST['search'] ?? '');
    if ($query === '') {
        sendJson(['success' => false, 'message' => 'Enter a search term']);
    }

    $userId = $_SESSION['user_id'] ?? 0;
    $searchTerm = '%' . $query . '%';
    $stmt = $conn->prepare(
        'SELECT p.id, p.title, p.photo_description, p.url, p.thumbnail, p.uploaded_at, a.title AS album_title, u.username AS owner,
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
         WHERE (p.title LIKE ? OR p.photo_description LIKE ? OR a.title LIKE ? OR p.uploaded_at LIKE ? OR u.username LIKE ?)
           AND (p.is_public = 1 OR p.user_id = ?)
         ORDER BY p.uploaded_at DESC
         LIMIT 30'
    );
    $stmt->execute([$userId, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $userId]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendJson(['success' => true, 'results' => $results]);
}

function addPhoto($conn) {
    if (!isset($_SESSION['user_id'])) {
        sendJson(['success' => false, 'message' => 'Login required']);
    }

    $title = htmlspecialchars(trim($_POST['title'] ?? ''));
    $description = htmlspecialchars(trim($_POST['photo_description'] ?? ''));
    $albumTitle = htmlspecialchars(trim($_POST['album_title'] ?? 'My album'));
    $url = trim($_POST['url'] ?? '');
    $isPublic = isset($_POST['is_public']) ? 1 : 0;
    $file = $_FILES['photo_file'] ?? null;

    if (empty($title) || empty($albumTitle)) {
        sendJson(['success' => false, 'message' => 'Title and album are required']);
    }

    if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
        if (empty($url)) {
            sendJson(['success' => false, 'message' => 'Either upload media or provide a URL']);
        }
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            sendJson(['success' => false, 'message' => 'Please provide a valid URL']);
        }
        $photoUrl = $url;
        $thumbnail = $url;
        $filename = basename(parse_url($url, PHP_URL_PATH) ?: 'media');
    } else {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            sendJson(['success' => false, 'message' => getUploadErrorMessage($file['error'])]);
        }

        $allowedMimes = [
            'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp',
            'video/mp4', 'video/webm', 'video/ogg', 'video/quicktime',
            'video/x-msvideo', 'video/x-ms-wmv', 'video/x-matroska', 'video/3gpp', 'video/3gpp2'
        ];
        $allowedExtensions = [
            'jpg', 'jpeg', 'png', 'gif', 'webp',
            'mp4', 'webm', 'ogg', 'mov', 'qt', 'avi', 'wmv', 'mkv', '3gp', '3g2'
        ];

        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $mimeValid = !empty($file['type']) && in_array($file['type'], $allowedMimes, true);
        $extValid = in_array($fileExtension, $allowedExtensions, true);
        $isVideoFile = in_array($fileExtension, ['mp4', 'webm', 'ogg', 'mov', 'qt', 'avi', 'wmv', 'mkv', '3gp', '3g2'], true)
            || str_starts_with($file['type'] ?? '', 'video/');

        if ($isVideoFile && $file['size'] > 8 * 1024 * 1024) {
            sendJson(['success' => false, 'message' => 'Video file must be 8MB or smaller']);
        }

        if (!$mimeValid && !$extValid) {
            sendJson(['success' => false, 'message' => 'Only JPG, PNG, GIF, WEBP, MP4, WEBM, OGG, MOV, AVI, WMV, and MKV files are allowed']);
        }

        $uploadDir = __DIR__ . '/../uploads';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
            sendJson(['success' => false, 'message' => 'Unable to create upload directory']);
        }

        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($file['name']));
        $uniqueName = time() . '_' . bin2hex(random_bytes(6)) . '_' . $safeName;
        $destination = $uploadDir . '/' . $uniqueName;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            sendJson(['success' => false, 'message' => 'Unable to store uploaded file']);
        }

        $photoUrl = 'uploads/' . $uniqueName;
        $thumbnail = $photoUrl;
        $filename = $uniqueName;
    }

    $userId = $_SESSION['user_id'];
    $stmt = $conn->prepare('SELECT id FROM album WHERE user_id = ? AND title = ?');
    $stmt->execute([$userId, $albumTitle]);
    $album = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($album) {
        $albumId = $album['id'];
    } else {
        $insertAlbum = $conn->prepare('INSERT INTO album (user_id, title, album_description, is_public) VALUES (?, ?, ?, 1)');
        $insertAlbum->execute([$userId, $albumTitle, 'Created from dashboard']);
        $albumId = $conn->lastInsertId();
    }

    $insertPhoto = $conn->prepare(
        'INSERT INTO photos (album_id, user_id, title, photo_description, filename, url, thumbnail, is_public)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $insertPhoto->execute([$albumId, $userId, $title, $description, $filename, $photoUrl, $thumbnail, $isPublic]);

    sendJson(['success' => true, 'message' => 'Photo added successfully']);
}

function toggleLike($conn) {
    if (!isset($_SESSION['user_id'])) {
        sendJson(['success' => false, 'message' => 'Login required']);
    }

    $photoId = intval($_POST['photo_id'] ?? 0);
    if ($photoId <= 0) {
        sendJson(['success' => false, 'message' => 'Invalid photo ID']);
    }

    $userId = $_SESSION['user_id'];
    $check = $conn->prepare('SELECT id FROM photo_likes WHERE photo_id = ? AND user_id = ?');
    $check->execute([$photoId, $userId]);
    $existing = $check->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $delete = $conn->prepare('DELETE FROM photo_likes WHERE id = ?');
        $delete->execute([$existing['id']]);
        $liked = false;
    } else {
        $insert = $conn->prepare('INSERT IGNORE INTO photo_likes (photo_id, user_id) VALUES (?, ?)');
        $insert->execute([$photoId, $userId]);
        $liked = true;
    }

    $count = $conn->prepare('SELECT COUNT(*) FROM photo_likes WHERE photo_id = ?');
    $count->execute([$photoId]);
    $likesCount = (int) $count->fetchColumn();

    sendJson(['success' => true, 'photo_id' => $photoId, 'likes_count' => $likesCount, 'liked' => $liked]);
}

function updateSettings($conn) {
    if (!isset($_SESSION['user_id'])) {
        sendJson(['success' => false, 'message' => 'Login required']);
    }

    $userId = $_SESSION['user_id'];
    $username = htmlspecialchars(trim($_POST['username'] ?? ''));
    $email = htmlspecialchars(trim($_POST['email'] ?? ''));
    $currentPwd = trim($_POST['current_password'] ?? '');
    $newPwd = trim($_POST['new_password'] ?? '');
    $confirmPwd = trim($_POST['confirm_password'] ?? '');

    if (empty($username) || empty($email) || empty($currentPwd)) {
        sendJson(['success' => false, 'message' => 'Username, email and current key are required']);
    }

    $userStmt = $conn->prepare('SELECT * FROM users WHERE id = ?');
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($currentPwd, $user['pwd'])) {
        sendJson(['success' => false, 'message' => 'Current key is incorrect']);
    }

    if ($newPwd !== '' && $newPwd !== $confirmPwd) {
        sendJson(['success' => false, 'message' => 'New keys do not match']);
    }

    try {
        $updateSql = 'UPDATE users SET username = ?, email = ?';
        $params = [$username, $email];

        if ($newPwd !== '') {
            $updateSql .= ', pwd = ?';
            $params[] = password_hash($newPwd, PASSWORD_DEFAULT);
        }

        $updateSql .= ' WHERE id = ?';
        $params[] = $userId;

        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->execute($params);

        $_SESSION['username'] = $username;
        sendJson(['success' => true, 'message' => 'Settings updated successfully']);
    } catch (PDOException $e) {
        $errorMessage = strpos($e->getMessage(), '23000') !== false
            ? 'Username or email is already taken'
            : 'Unable to update settings';
        sendJson(['success' => false, 'message' => $errorMessage]);
    }
}

function updatePhoto($conn) {
    if (!isset($_SESSION['user_id'])) {
        sendJson(['success' => false, 'message' => 'Login required']);
    }

    $photoId = intval($_POST['photo_id'] ?? 0);
    $title = htmlspecialchars(trim($_POST['title'] ?? ''));
    $description = htmlspecialchars(trim($_POST['photo_description'] ?? ''));

    if ($photoId <= 0 || $title === '') {
        sendJson(['success' => false, 'message' => 'Invalid photo update data']);
    }

    $userId = $_SESSION['user_id'];
    $check = $conn->prepare('SELECT user_id FROM photos WHERE id = ?');
    $check->execute([$photoId]);
    $photo = $check->fetch(PDO::FETCH_ASSOC);

    if (!$photo || $photo['user_id'] !== $userId) {
        sendJson(['success' => false, 'message' => 'Photo not found or not owned by you']);
    }

    $stmt = $conn->prepare('UPDATE photos SET title = ?, photo_description = ? WHERE id = ?');
    $stmt->execute([$title, $description, $photoId]);

    sendJson(['success' => true, 'message' => 'Photo updated successfully']);
}

function deletePhoto($conn) {
    if (!isset($_SESSION['user_id'])) {
        sendJson(['success' => false, 'message' => 'Login required']);
    }

    $photoId = intval($_POST['photo_id'] ?? 0);
    if ($photoId <= 0) {
        sendJson(['success' => false, 'message' => 'Invalid photo ID']);
    }

    $userId = $_SESSION['user_id'];
    $check = $conn->prepare('SELECT filename, url, user_id FROM photos WHERE id = ?');
    $check->execute([$photoId]);
    $photo = $check->fetch(PDO::FETCH_ASSOC);

    if (!$photo || $photo['user_id'] !== $userId) {
        sendJson(['success' => false, 'message' => 'Photo not found or not owned by you']);
    }

    $deleteLikes = $conn->prepare('DELETE FROM photo_likes WHERE photo_id = ?');
    $deleteLikes->execute([$photoId]);

    $deletePhoto = $conn->prepare('DELETE FROM photos WHERE id = ?');
    $deletePhoto->execute([$photoId]);

    $localPath = __DIR__ . '/../' . $photo['url'];
    if (strpos($photo['url'], 'uploads/') === 0 && file_exists($localPath)) {
        @unlink($localPath);
    }

    sendJson(['success' => true, 'message' => 'Photo deleted successfully']);
}

function getUploadErrorMessage($errorCode) {
    $uploadLimit = ini_get('upload_max_filesize') ?: 'unknown';
    return match ($errorCode) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => "Upload too large. Choose a smaller file (server limit is {$uploadLimit}).",
        UPLOAD_ERR_PARTIAL => 'Upload was interrupted. Please try again.',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder on server.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write upload to disk.',
        UPLOAD_ERR_EXTENSION => 'A PHP extension blocked the upload.',
        default => 'Upload failed. Try a different file.',
    };
}

function sendJson($payload) {
    echo json_encode($payload);
    exit;
}
