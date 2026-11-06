<?php
// login.php - one login for the whole system (Adviser, Treasurer, Auditor, Business Manager, Mayor, Vice Mayor)
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Already signed in? Go straight to your dashboard.
if (isset($_SESSION['adviser_id'])) { header('Location: admin/adviser/dashboard.php'); exit; }
if (isset($_SESSION['officer_id'])) { header('Location: admin/officer/dashboard.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identity = trim($_POST['identity'] ?? '');
    $password = $_POST['password'] ?? '';
    $done = false;

    // 1) Officers sign in with their email (Treasurer, Auditor, Business Manager, Mayor, Vice Mayor)
    $stmt = $pdo->prepare("SELECT * FROM officers WHERE email = ?");
    $stmt->execute([$identity]);
    $officer = $stmt->fetch();

    if ($officer && password_verify($password, $officer['password_hash'])) {
        $done = true;
        if ($officer['is_active'] == 0) {
            $error = "Your account is inactive. Please contact the adviser.";
        } else {
            $_SESSION = array();
            $_SESSION['officer_id'] = $officer['id'];
            $_SESSION['officer_name'] = $officer['name'];
            $_SESSION['officer_position'] = $officer['position'];
            header('Location: admin/officer/dashboard.php'); // routes to the right dashboard by position
            exit;
        }
    }

    // 2) The adviser signs in with a username
    if (!$done) {
        $stmt = $pdo->prepare("SELECT * FROM adviser_account WHERE username = ?");
        $stmt->execute([$identity]);
        $adviser = $stmt->fetch();

        if ($adviser && password_verify($password, $adviser['password_hash'])) {
            $done = true;
            $_SESSION = array();
            $_SESSION['adviser_id'] = $adviser['id'];
            $_SESSION['adviser_name'] = $adviser['name'];
            header('Location: admin/adviser/dashboard.php');
            exit;
        }
    }

    if (!$done) $error = "Invalid email/username or password.";
}

// Dynamic Branding
$sysSettings = getSettings($pdo);
$sysLogoUrl = ltrim($sysSettings['logo_path'], '/');
$sysLogoPath = __DIR__ . '/' . $sysSettings['logo_path'];
$themeColor = $sysSettings['primary_color'] ?? '#1a3c5e';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - <?= htmlspecialchars($sysSettings['org_short_name']) ?></title>
    <link rel="stylesheet" href="assets/css/login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root { --theme-primary: <?= htmlspecialchars($themeColor) ?>; }
        .login-logo h2 { color: var(--theme-primary) !important; }
        .btn-login { background-color: var(--theme-primary) !important; }
        .icon-circle i { color: var(--theme-primary) !important; }
        .login-card h3 { color: var(--theme-primary) !important; }
        .login-links { margin-top: 16px; display: flex; flex-direction: column; gap: 8px; text-align: center; }
        .login-links a { color: #64748b; font-size: 13px; text-decoration: none; }
        .login-links a:hover { color: var(--theme-primary); }
        @media (max-width: 480px) { .login-card { width: 92vw; padding: 32px 22px; } }
    </style>
</head>
<body>
<div class="login-container">
    <div class="login-logo">
        <?php if (file_exists($sysLogoPath)): ?>
            <img src="<?= htmlspecialchars($sysLogoUrl) ?>" alt="Logo">
        <?php else: ?>
            <img src="assets/images/logo.png" alt="Logo">
        <?php endif; ?>
        <h2><?= htmlspecialchars($sysSettings['org_short_name']) ?></h2>
        <p>Transparency Fund System</p>
    </div>
    <div class="login-card">
        <div class="icon-circle"><i class="fas fa-user-shield"></i></div>
        <h3>Sign in</h3>
        <p class="role-label">Adviser &middot; Treasurer &middot; Auditor &middot; Business Manager &middot; Mayor &middot; Vice Mayor</p>
        <?php if ($error): ?><div class="error-box"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label for="identity">Email or username</label>
                <div class="input-with-icon"><i class="fas fa-user"></i><input type="text" id="identity" name="identity" placeholder="Officers: email &middot; Adviser: username" value="<?= htmlspecialchars($_POST['identity'] ?? '') ?>" autocomplete="username" required autofocus></div>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-with-icon"><i class="fas fa-lock"></i><input type="password" id="password" name="password" placeholder="Enter password" autocomplete="current-password" required></div>
            </div>
            <button type="submit" class="btn-login">Log In</button>
        </form>
        <div class="login-links">
            <a href="admin/adviser/forgot_password.php">Forgot password? (Adviser)</a>
            <a href="public/index.php"><i class="fas fa-arrow-left"></i> Back to public page</a>
        </div>
    </div>
    <div class="login-footer">&copy; <?= date('Y') ?> <?= htmlspecialchars($sysSettings['org_name']) ?></div>
</div>
</body>
</html>
