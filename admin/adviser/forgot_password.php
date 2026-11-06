<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

$message = '';
$error = '';

// Fetch dynamic settings from the database
$sysSettings = getSettings($pdo);
$dbRecoveryKey = $sysSettings['recovery_key'] ?? 'BSIT-ADMIN-2026';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $recoveryKey = trim($_POST['recovery_key']);
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];

    if (empty($username) || empty($recoveryKey) || empty($newPassword)) {
        $error = "All fields are required.";
    } elseif ($recoveryKey !== $dbRecoveryKey) {
        $error = "Invalid Recovery Key. Please contact the System Administrator.";
    } elseif (strlen($newPassword) < 8) {
        $error = "New password must be at least 8 characters long.";
    } elseif ($newPassword !== $confirmPassword) {
        $error = "Passwords do not match.";
    } else {
        // Check if adviser exists
        $stmt = $pdo->prepare("SELECT id FROM adviser_account WHERE username = ?");
        $stmt->execute([$username]);
        $adviser = $stmt->fetch();

        if ($adviser) {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $updateStmt = $pdo->prepare("UPDATE adviser_account SET password_hash = ? WHERE id = ?");
            $updateStmt->execute([$newHash, $adviser['id']]);
            $message = "Password successfully reset! You can now <a href='../../login.php' style='color:#155724; font-weight:bold;'>Login here</a>.";
        } else {
            $error = "Username not found.";
        }
    }
}

// Dynamic Branding for UI
$sysLogoUrl = '../../' . ltrim($sysSettings['logo_path'], '/');
$sysLogoPath = realpath(__DIR__ . '/../../') . '/' . $sysSettings['logo_path'];
$themeColor = $sysSettings['primary_color'] ?? '#1a3c5e';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Forgot Password - <?= htmlspecialchars($sysSettings['org_short_name']) ?></title>
    <link rel="stylesheet" href="../../assets/css/login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root { --theme-primary: <?= htmlspecialchars($themeColor) ?>; }
        .login-logo h2 { color: var(--theme-primary) !important; }
        .btn-login { background-color: var(--theme-primary) !important; }
        .icon-circle i { color: var(--theme-primary) !important; }
        .login-card h3 { color: var(--theme-primary) !important; }
        
        .message-box { margin-bottom: 15px; padding: 12px; border-radius: 8px; font-size: 13px; text-align: center; line-height: 1.4; }
        .msg-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .msg-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>
<div class="login-container">
    <div class="login-logo">
        <?php if (file_exists($sysLogoPath)): ?>
            <img src="<?= $sysLogoUrl ?>" alt="Logo">
        <?php else: ?>
            <img src="../../assets/images/logo.png" alt="Logo">
        <?php endif; ?>
        <h2><?= htmlspecialchars($sysSettings['org_short_name']) ?></h2>
        <p>Transparency Fund System</p>
    </div>
    <div class="login-card">
        <div class="icon-circle"><i class="fas fa-unlock-alt"></i></div>
        <h3>Account Recovery</h3>
        <p class="role-label">Enter your details to reset password</p>
        
        <?php if ($message): ?><div class="message-box msg-success"><?= $message ?></div><?php endif; ?>
        <?php if ($error): ?><div class="message-box msg-error"><?= $error ?></div><?php endif; ?>

        <?php if (!$message): ?>
        <form method="POST">
            <div class="form-group">
                <label>Username</label>
                <div class="input-with-icon"><i class="fas fa-user"></i><input type="text" name="username" placeholder="Enter username" required></div>
            </div>
            <div class="form-group">
                <label>Master Recovery Key</label>
                <div class="input-with-icon"><i class="fas fa-key"></i><input type="password" name="recovery_key" placeholder="Enter admin recovery key" required></div>
            </div>
            <div class="form-group">
                <label>New Password</label>
                <div class="input-with-icon"><i class="fas fa-lock"></i><input type="password" name="new_password" placeholder="Min 8 characters" required></div>
            </div>
            <div class="form-group">
                <label>Confirm New Password</label>
                <div class="input-with-icon"><i class="fas fa-lock"></i><input type="password" name="confirm_password" placeholder="Retype new password" required></div>
            </div>
            <button type="submit" class="btn-login">Reset Password</button>
        </form>
        <?php endif; ?>
        
        <div style="margin-top: 20px;">
            <a href="../../login.php" style="color: #64748b; text-decoration: none; font-size: 13px;"><i class="fas fa-arrow-left"></i> Back to Login</a>
        </div>
    </div>
    <div class="login-footer">&copy; <?= date('Y') ?> <?= htmlspecialchars($sysSettings['org_name']) ?></div>
</div>
</body>
</html>