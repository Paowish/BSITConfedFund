<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check login
if (!isset($_SESSION['adviser_id']) && !isset($_SESSION['officer_id'])) {
    header('Location: ../login.php');
    exit;
}

// Determine user role
if (isset($_SESSION['adviser_id'])) {
    $role = 'adviser';
    $table = 'adviser_account';
    $userId = $_SESSION['adviser_id'];
    $userName = $_SESSION['adviser_name'] ?? 'User';
    $userPosition = 'Adviser';
    $cssFile = 'adviser.css';
} else {
    $role = 'officer';
    $table = 'officers';
    $userId = $_SESSION['officer_id'];
    $userName = $_SESSION['officer_name'] ?? 'User';
    $userPosition = $_SESSION['officer_position'] ?? 'Officer';
    $cssFile = ($userPosition === 'Auditor') ? 'auditor.css' : 'officer.css';
}

$message = '';
$error = '';

// Get current profile picture
$stmt = $pdo->prepare("SELECT profile_picture FROM $table WHERE id = ?");
$stmt->execute([$userId]);
$currentPic = $stmt->fetchColumn() ?: null;

// Handle upload/remove
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'upload') {
        if (empty($_FILES['profile_picture']['name'])) {
            $error = "Please select an image.";
        } else {
            $fileError = $_FILES['profile_picture']['error'];

            if ($fileError === UPLOAD_ERR_OK) {
                $uploadDir = realpath(__DIR__ . '/../') . '/uploads/profiles/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $maxSize = 2 * 1024 * 1024; // 2MB

                $fileTmp = $_FILES['profile_picture']['tmp_name'];
                $fileSize = $_FILES['profile_picture']['size'];

                if ($fileSize > $maxSize) {
                    $error = "File too large. Maximum 2MB.";
                } else {
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mime = $finfo->file($fileTmp);

                    if (!in_array($mime, $allowedTypes)) {
                        $error = "Invalid file type. Only JPG, PNG, GIF, or WEBP allowed.";
                    } else {
                        $ext = match($mime) {
                            'image/jpeg' => 'jpg',
                            'image/png'  => 'png',
                            'image/gif'  => 'gif',
                            'image/webp' => 'webp',
                            default      => 'jpg'
                        };
                        $fileName = $table . '_' . $userId . '_' . time() . '.' . $ext;
                        $targetPath = $uploadDir . $fileName;

                        if (move_uploaded_file($fileTmp, $targetPath)) {
                            // Delete old file if exists
                            if (!empty($currentPic)) {
                                $oldFull = $_SERVER['DOCUMENT_ROOT'] . '/bsit-fund-system-v2/' . ltrim($currentPic, '/');
                                if (file_exists($oldFull)) @unlink($oldFull);
                            }

                            $newPath = 'uploads/profiles/' . $fileName;
                            $pdo->prepare("UPDATE $table SET profile_picture = ? WHERE id = ?")
                                ->execute([$newPath, $userId]);
                            $currentPic = $newPath;
                            $message = "Profile picture updated!";
                        } else {
                            $error = "Failed to save file.";
                        }
                    }
                }
            } else {
                $error = "Upload error (code: $fileError).";
            }
        }
    } elseif ($action === 'remove') {
        if (!empty($currentPic)) {
            $oldFull = $_SERVER['DOCUMENT_ROOT'] . '/bsit-fund-system-v2/' . ltrim($currentPic, '/');
            if (file_exists($oldFull)) @unlink($oldFull);
        }
        $pdo->prepare("UPDATE $table SET profile_picture = NULL WHERE id = ?")->execute([$userId]);
        $currentPic = null;
        $message = "Profile picture removed.";
    }
}

// Check if current pic file exists for display
$currentPicExists = false;
if (!empty($currentPic)) {
    $fullCheck = $_SERVER['DOCUMENT_ROOT'] . '/bsit-fund-system-v2/' . ltrim($currentPic, '/');
    $currentPicExists = file_exists($fullCheck);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>My Profile - BSIT Fund System</title>
    <link rel="stylesheet" href="../assets/css/<?= $cssFile ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .profile-card {
            background: #fff;
            border-radius: 14px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 12px rgba(0,0,0,0.04);
            padding: 40px 45px;
            max-width: 500px;
            margin: 0 auto;
            text-align: center;
        }
        .profile-card h2 {
            color: #1a3c5e;
            margin-bottom: 6px;
            font-size: 22px;
        }
        .profile-card .subtitle {
            color: #64748b;
            font-size: 13.5px;
            margin-bottom: 30px;
        }
        .avatar-wrapper {
            position: relative;
            display: inline-block;
            margin-bottom: 24px;
        }
        .avatar-big {
            width: 130px;
            height: 130px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 700;
            font-size: 52px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
        }
        .avatar-upload-btn {
            position: absolute;
            bottom: 0;
            right: 0;
            background: #1a3c5e;
            color: #fff;
            border: 3px solid #fff;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 15px;
            transition: background 0.2s;
        }
        .avatar-upload-btn:hover {
            background: #2c5282;
        }
        .avatar-upload-btn input {
            display: none;
        }
        .profile-info {
            text-align: left;
            margin: 25px 0 20px;
            background: #f8fafc;
            border-radius: 10px;
            padding: 18px 22px;
            border-left: 3px solid #2980b9;
        }
        .profile-info p {
            font-size: 13.5px;
            color: #334155;
            margin: 5px 0;
        }
        .profile-info strong {
            color: #1a3c5e;
            display: inline-block;
            min-width: 90px;
        }
        .btn-danger {
            background: #fff;
            color: #e74c3c;
            border: 1px solid #fecaca;
            padding: 11px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 13.5px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-danger:hover {
            background: #fef2f2;
        }
        .alert {
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13.5px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            text-align: left;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #27ae60;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #e74c3c;
        }
        .hint {
            font-size: 12px;
            color: #94a3b8;
            margin-top: 12px;
        }
    </style>
</head>
<body>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/bsit-fund-system-v2/includes/sidebar.php'; ?>

<div class="main-area">
    <div class="top-bar">
        <div class="top-bar-left">
            <h1>My Profile</h1>
        </div>
        <div class="user-menu" id="userMenu">
            <?= renderAvatar($userName, $currentPic, 38) ?>
            <div class="dropdown" id="userDropdown">
                <a href="profile.php"><i class="fas fa-user-circle"></i> My Profile</a>
                <a href="change_password.php"><i class="fas fa-key"></i> Change Password</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </div>

    <div class="page-content">
        <div class="profile-card">
            <?php if ($message): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <h2>Profile Picture</h2>
            <p class="subtitle">Upload a profile picture or use your initials</p>

            <form method="POST" enctype="multipart/form-data" id="uploadForm">
                <input type="hidden" name="action" value="upload">

                <div class="avatar-wrapper">
                    <label for="fileInput" style="cursor:pointer;">
                        <?php if ($currentPicExists): ?>
                            <img src="/bsit-fund-system-v2/<?= htmlspecialchars(ltrim($currentPic, '/')) ?>" class="avatar-big" alt="Profile">
                        <?php else: ?>
                            <div class="avatar-big" style="background: <?= getAvatarColor($userName) ?>;">
                                <?= htmlspecialchars(getInitials($userName)) ?>
                            </div>
                        <?php endif; ?>
                    </label>
                    <label class="avatar-upload-btn" title="Upload new picture">
                        <i class="fas fa-camera"></i>
                        <input type="file" name="profile_picture" id="fileInput" accept="image/jpeg,image/png,image/gif,image/webp" onchange="document.getElementById('uploadForm').submit();">
                    </label>
                </div>
            </form>

            <div class="profile-info">
                <p><strong>Name:</strong> <?= htmlspecialchars($userName) ?></p>
                <p><strong>Role:</strong> <?= htmlspecialchars($userPosition) ?></p>
            </div>

            <?php if ($currentPicExists): ?>
                <form method="POST" onsubmit="return confirm('Remove profile picture?');">
                    <input type="hidden" name="action" value="remove">
                    <button type="submit" class="btn-danger">
                        <i class="fas fa-trash"></i> Remove Picture
                    </button>
                </form>
            <?php endif; ?>

            <p class="hint"><i class="fas fa-info-circle"></i> Accepted: JPG, PNG, GIF, WEBP &middot; Max 2MB</p>
        </div>
    </div>
</div>

<script>
    document.getElementById('userMenu').addEventListener('click', function(e) {
        e.stopPropagation();
        this.classList.toggle('show');
    });
    window.addEventListener('click', function() {
        document.getElementById('userMenu').classList.remove('show');
    });
</script>
</body>
</html>