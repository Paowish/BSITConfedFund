<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Determine role and load appropriate CSS
if (!isset($_SESSION['adviser_id']) && !isset($_SESSION['officer_id'])) {
    header('Location: ../login.php');
    exit;
}

if (isset($_SESSION['adviser_id'])) {
    $role = 'adviser';
    $table = 'adviser_account';
    $idColumn = 'id';
    $userId = $_SESSION['adviser_id'];
    $userName = $_SESSION['adviser_name'];
    $cssFile = 'adviser.css';
} else {
    $role = 'officer';
    $position = $_SESSION['officer_position'] ?? '';
    $table = 'officers';
    $idColumn = 'id';
    $userId = $_SESSION['officer_id'];
    $userName = $_SESSION['officer_name'];

    if ($position === 'Auditor') {
        $cssFile = 'auditor.css';
    } else {
        $cssFile = 'officer.css';
    }
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];

    $stmt = $pdo->prepare("SELECT password_hash FROM $table WHERE $idColumn = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
        $error = "Current password is incorrect.";
    } elseif (strlen($newPassword) < 8) {
        $error = "New password must be at least 8 characters long.";
    } elseif ($newPassword !== $confirmPassword) {
        $error = "New passwords do not match.";
    } else {
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $updateStmt = $pdo->prepare("UPDATE $table SET password_hash = ? WHERE $idColumn = ?");
        $updateStmt->execute([$newHash, $userId]);
        $message = "Password changed successfully!";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Change Password - BSIT Fund System</title>
    <link rel="stylesheet" href="../assets/css/<?= $cssFile ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .card {
            background: #ffffff;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            padding: 35px;
            max-width: 750px;
            margin: 0 auto;
        }
        .card h3 {
            margin-top: 0;
            color: #1a3c5e;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 18px;
            font-weight: 700;
        }
        
        /* NEW LAYOUT: Inline Row */
        .cp-row {
            display: flex;
            align-items: flex-start;
            margin-bottom: 20px;
        }
        .cp-label {
            width: 200px; /* Fixed width for labels */
            flex-shrink: 0;
            font-weight: 600;
            font-size: 14px;
            color: #334155;
            padding-top: 11px; /* Align with input text */
            margin-bottom: 0;
        }
        .cp-input-wrap {
            flex: 1;
            position: relative;
        }
        .cp-input-wrap input {
            width: 100%;
            padding: 10px 45px 10px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 14px;
            box-sizing: border-box;
            transition: all 0.2s;
        }
        .cp-input-wrap input:focus {
            outline: none;
            border-color: var(--theme-primary, #1a3c5e);
            box-shadow: 0 0 0 3px rgba(26,60,94,0.1);
        }
        .toggle-eye {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #94a3b8;
            font-size: 16px;
            background: none;
            border: none;
            padding: 4px;
            transition: color 0.2s;
        }
        .toggle-eye:hover { color: #1a3c5e; }
        
        /* STRENGTH BAR */
        .strength-bar-container {
            height: 6px;
            background: #e2e8f0;
            border-radius: 3px;
            overflow: hidden;
            margin-bottom: 6px;
        }
        .strength-bar {
            height: 100%;
            width: 0%;
            transition: width 0.3s, background-color 0.3s;
            border-radius: 3px;
        }
        .strength-label {
            font-size: 12px;
            font-weight: 600;
            text-align: right;
            min-height: 16px;
        }

        /* HORIZONTAL REQUIREMENTS LIST */
        .requirements-list {
            list-style: none;
            padding: 0;
            margin: 0;
            font-size: 12px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .requirements-list li {
            color: #94a3b8;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .requirements-list li i { font-size: 13px; }
        .requirements-list li.valid { color: #27ae60; }
        .requirements-list li.invalid { color: #cbd5e1; }

        .success { color: #155724; background: #d4edda; padding: 12px; border-radius: 8px; border-left: 4px solid #27ae60; margin-bottom: 20px; }
        .error { color: #721c24; background: #f8d7da; padding: 12px; border-radius: 8px; border-left: 4px solid #e74c3c; margin-bottom: 20px; }
        
        .user-info {
            background: #f8fafc;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            font-size: 14px;
            color: #475569;
            border-left: 3px solid var(--theme-primary, #1a3c5e);
        }
        .user-info strong { color: #1a3c5e; }

        @media (max-width: 600px) {
            .cp-row { flex-direction: column; }
            .cp-label { width: 100%; padding-top: 0; margin-bottom: 6px; }
            .cp-btn-row { margin-left: 0 !important; }
        }
    </style>
</head>
<body>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/bsit-fund-system-v2/includes/sidebar.php'; ?>

<div class="main-area">
    <?php 
    $pageTitle = 'Change Password';
    $pageSubtitle = 'Welcome, <strong>' . htmlspecialchars($userName) . '</strong>';
    $basePath = '../';
    include $_SERVER['DOCUMENT_ROOT'] . '/bsit-fund-system-v2/includes/topbar.php'; 
    ?>

    <div class="page-content">
        <div class="card">
            <h3><i class="fas fa-key"></i> Change Password</h3>
            <div class="user-info">
                Logged in as: <strong><?= htmlspecialchars($userName) ?></strong>
                (<?= ucfirst($role) ?><?= $role === 'officer' ? ' - ' . htmlspecialchars($position) : '' ?>)
            </div>

            <?php if ($message): ?><div class="success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

            <form method="POST" id="changePasswordForm">
                
                <!-- Current Password -->
                <div class="cp-row">
                    <label class="cp-label">Current Password</label>
                    <div class="cp-input-wrap">
                        <input type="password" name="current_password" id="currentPassword" required>
                        <button type="button" class="toggle-eye" onclick="togglePassword('currentPassword', this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <!-- New Password -->
                <div class="cp-row">
                    <label class="cp-label">New Password</label>
                    <div class="cp-input-wrap">
                        <input type="password" name="new_password" id="newPassword" required oninput="checkPasswordStrength()">
                        <button type="button" class="toggle-eye" onclick="togglePassword('newPassword', this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <!-- Strength Bar & Requirements (Aligned under the input) -->
                <div class="cp-row" style="margin-bottom: 15px;">
                    <div class="cp-label"></div>
                    <div class="cp-input-wrap">
                        <div class="strength-bar-container">
                            <div class="strength-bar" id="strengthBar"></div>
                        </div>
                        <div class="strength-label" id="strengthLabel"></div>
                        
                        <ul class="requirements-list">
                            <li id="req-length" class="invalid"><i class="fas fa-times-circle"></i> 8+ chars</li>
                            <li id="req-upper" class="invalid"><i class="fas fa-times-circle"></i> Uppercase</li>
                            <li id="req-lower" class="invalid"><i class="fas fa-times-circle"></i> Lowercase</li>
                            <li id="req-number" class="invalid"><i class="fas fa-times-circle"></i> Number</li>
                            <li id="req-special" class="invalid"><i class="fas fa-times-circle"></i> Special</li>
                        </ul>
                    </div>
                </div>

                <!-- Confirm Password -->
                <div class="cp-row">
                    <label class="cp-label">Confirm New Password</label>
                    <div class="cp-input-wrap">
                        <input type="password" name="confirm_password" id="confirmPassword" required>
                        <button type="button" class="toggle-eye" onclick="togglePassword('confirmPassword', this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="cp-row cp-btn-row" style="margin-left: 200px; margin-top: 30px; margin-bottom: 0;">
                    <button type="submit" class="btn-submit" style="width: 100%; margin: 0; padding: 12px;">
                        <i class="fas fa-save"></i> Update Password
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>

<?php include '../includes/shared_ui.php'; ?>

<script>
    function togglePassword(inputId, btn) {
        const input = document.getElementById(inputId);
        const icon = btn.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }

    function checkPasswordStrength() {
        const password = document.getElementById('newPassword').value;
        const strengthBar = document.getElementById('strengthBar');
        const strengthLabel = document.getElementById('strengthLabel');

        const hasLength = password.length >= 8;
        const hasUpper = /[A-Z]/.test(password);
        const hasLower = /[a-z]/.test(password);
        const hasNumber = /[0-9]/.test(password);
        const hasSpecial = /[!@#$%^&*(),.?":{}|<>_\-+=~`\[\]\\\/;']/.test(password);

        updateReq('req-length', hasLength);
        updateReq('req-upper', hasUpper);
        updateReq('req-lower', hasLower);
        updateReq('req-number', hasNumber);
        updateReq('req-special', hasSpecial);

        let score = 0;
        if (hasLength) score++;
        if (hasUpper) score++;
        if (hasLower) score++;
        if (hasNumber) score++;
        if (hasSpecial) score++;

        let strength = '';
        let color = '';
        let percent = 0;

        if (password.length === 0) {
            strength = ''; color = '#eee'; percent = 0;
        } else if (!hasLength) {
            strength = 'Very Weak'; color = '#e74c3c'; percent = 15;
        } else if (score <= 2) {
            strength = 'Weak'; color = '#e67e22'; percent = 40;
        } else if (score === 3) {
            strength = 'Medium'; color = '#f39c12'; percent = 60;
        } else if (score === 4) {
            strength = 'Strong'; color = '#27ae60'; percent = 80;
        } else {
            strength = 'Very Strong'; color = '#16a085'; percent = 100;
        }

        strengthBar.style.width = percent + '%';
        strengthBar.style.backgroundColor = color;
        strengthLabel.textContent = strength;
        strengthLabel.style.color = color;
    }

    function updateReq(id, isValid) {
        const el = document.getElementById(id);
        const icon = el.querySelector('i');
        if (isValid) {
            el.classList.remove('invalid');
            el.classList.add('valid');
            icon.classList.remove('fa-times-circle');
            icon.classList.add('fa-check-circle');
        } else {
            el.classList.remove('valid');
            el.classList.add('invalid');
            icon.classList.remove('fa-check-circle');
            icon.classList.add('fa-times-circle');
        }
    }

    document.getElementById('changePasswordForm').addEventListener('submit', function(e) {
        const newPass = document.getElementById('newPassword').value;
        const confirmPass = document.getElementById('confirmPassword').value;
        if (newPass.length < 8) {
            e.preventDefault();
            alert('New password must be at least 8 characters long.');
            return false;
        }
        if (newPass !== confirmPass) {
            e.preventDefault();
            alert('New passwords do not match.');
            return false;
        }
    });

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