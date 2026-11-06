<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireAdviser();

$message = '';
$error = '';
$currentPic = getCurrentUserProfilePic($pdo);
$settings = getSettings($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $org_name = trim($_POST['org_name'] ?? '');
    $org_short_name = trim($_POST['org_short_name'] ?? '');
    $primary_color = trim($_POST['primary_color'] ?? '#1a3c5e');
    $recovery_key = trim($_POST['recovery_key'] ?? '');
    $logo_path = $settings['logo_path'];

    if (empty($org_name) || empty($org_short_name) || empty($recovery_key)) {
        $error = "Organization name, short name, and recovery key are required.";
    } else {
        // Handle logo upload
        if (!empty($_FILES['logo']['name'])) {
            $uploadDir = realpath(__DIR__ . '/../../') . '/uploads/branding/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

            $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
            $fileTmp = $_FILES['logo']['tmp_name'];
            $fileError = $_FILES['logo']['error'];
            $fileSize = $_FILES['logo']['size'];

            if ($fileError === UPLOAD_ERR_OK) {
                if ($fileSize > 2 * 1024 * 1024) {
                    $error = "Logo must be less than 2MB.";
                } else {
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mime = $finfo->file($fileTmp);
                    if (in_array($mime, $allowedTypes)) {
                        $ext = match($mime) {
                            'image/jpeg' => 'jpg',
                            'image/png'  => 'png',
                            'image/webp' => 'webp',
                            default      => 'png'
                        };
                        $fileName = 'logo_' . time() . '.' . $ext;
                        $targetPath = $uploadDir . $fileName;
                        if (move_uploaded_file($fileTmp, $targetPath)) {
                            // Delete old logo if it exists and is not a default system file
                            if (!empty($settings['logo_path']) && file_exists(realpath(__DIR__ . '/../../') . '/' . $settings['logo_path'])) {
                                if (strpos($settings['logo_path'], 'assets/images') === false) {
                                    @unlink(realpath(__DIR__ . '/../../') . '/' . $settings['logo_path']);
                                }
                            }
                            $logo_path = 'uploads/branding/' . $fileName;
                        } else {
                            $error = "Failed to save logo.";
                        }
                    } else {
                        $error = "Invalid file type. Only JPG, PNG, WEBP allowed.";
                    }
                }
            }
        }

        if (empty($error)) {
            $stmt = $pdo->prepare("UPDATE settings SET org_name = ?, org_short_name = ?, logo_path = ?, primary_color = ?, recovery_key = ? WHERE id = 1");
            $stmt->execute([$org_name, $org_short_name, $logo_path, $primary_color, $recovery_key]);
            $message = "Customization updated successfully!";
            $settings = getSettings($pdo); // Reload settings
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Customization - BSIT Fund System</title>
    <link rel="stylesheet" href="../../assets/css/adviser.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .card { background:#fff; border-radius:12px; border:1px solid #e2e8f0; box-shadow:0 4px 6px -1px rgba(0,0,0,0.05); padding:35px; max-width:650px; margin:0 auto; }
        .card h3 { margin-top:0; color:#1a3c5e; margin-bottom:25px; font-size: 18px; font-weight:700; display:flex; align-items:center; gap:10px; }
        .form-group { margin-bottom:20px; }
        .form-group label { display:block; font-weight:600; margin-bottom:8px; color:#334155; font-size:14px; }
        .form-group input[type="text"],
        .form-group input[type="password"] { width:100%; padding:10px 14px; border:1px solid #cbd5e1; border-radius:8px; font-size:14px; box-sizing:border-box; transition:all 0.2s; }
        .form-group input:focus { outline:none; border-color:var(--theme-primary, #1a3c5e); box-shadow:0 0 0 3px rgba(26,60,94,0.1); }
        
        .password-wrapper { position: relative; }
        .password-wrapper input { padding-right: 45px !important; }
        .toggle-eye { position:absolute; right:12px; top:50%; transform:translateY(-50%); cursor:pointer; color:#94a3b8; font-size:16px; background:none; border:none; padding:4px; }
        .toggle-eye:hover { color:#1a3c5e; }

        /* Color Picker Style */
        .color-picker-wrapper { display: flex; align-items: center; gap: 12px; background: #f8fafc; padding: 12px; border-radius: 8px; border: 1px solid #e2e8f0; }
        .color-picker-wrapper input[type="color"] { width: 50px; height: 45px; border: none; border-radius: 6px; cursor: pointer; padding: 0; background: none; }
        .color-picker-wrapper input[type="color"]::-webkit-color-swatch-wrapper { padding: 0; }
        .color-picker-wrapper input[type="color"]::-webkit-color-swatch { border: 1px solid #ccc; border-radius: 6px; }
        .color-hint { font-size: 13px; color: #64748b; line-height: 1.4; }

        .current-logo { margin-top:10px; text-align:center; padding:20px; background:#f8fafc; border-radius:8px; border:1px dashed #cbd5e1; }
        .current-logo img { max-height:80px; max-width:200px; display:block; margin:0 auto 10px; object-fit: contain; }
        .current-logo p { margin:0; font-size:12px; color:#64748b; font-weight:600; }
        
        .btn-save { background:var(--theme-primary, #1a3c5e); color:#fff; border:none; padding:12px 24px; border-radius:8px; font-size:14px; font-weight:600; cursor:pointer; width:100%; transition: all 0.2s; }
        .btn-save:hover { opacity: 0.9; transform: translateY(-1px); box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        
        .alert { padding:12px 18px; border-radius:8px; margin-bottom:20px; font-size:13.5px; font-weight:500; display:flex; align-items:center; gap:10px; }
        .alert-success { background:#d4edda; color:#155724; border-left:4px solid #27ae60; }
        .alert-error { background:#f8d7da; color:#721c24; border-left:4px solid #e74c3c; }

        /* Section Divider */
        .section-divider { margin: 30px 0 25px; border-top: 1px solid #e2e8f0; position: relative; text-align: center; }
        .section-divider span { background: #fff; padding: 0 15px; color: #64748b; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; position: relative; top: -10px; }
    </style>
</head>
<body>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/bsit-fund-system-v2/includes/sidebar.php'; ?>

<div class="main-area">
    <?php 
    $pageTitle = 'System Customization';
    $pageSubtitle = 'Welcome, <strong>' . htmlspecialchars($_SESSION['adviser_name']) . '</strong>';
    $basePath = '../';
    include $_SERVER['DOCUMENT_ROOT'] . '/bsit-fund-system-v2/includes/topbar.php'; 
    ?>

    <div class="page-content">
        <div class="card">
            <h3><i class="fas fa-palette"></i> Branding & Appearance</h3>
            
            <?php if ($message): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Organization Name</label>
                    <input type="text" name="org_name" value="<?= htmlspecialchars($settings['org_name']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Organization Short Name (for sidebar)</label>
                    <input type="text" name="org_short_name" value="<?= htmlspecialchars($settings['org_short_name']) ?>" required>
                </div>

                <div class="form-group">
                    <label>Primary Color Theme</label>
                    <div class="color-picker-wrapper">
                        <input type="color" name="primary_color" value="<?= htmlspecialchars($settings['primary_color'] ?? '#1a3c5e') ?>">
                        <div class="color-hint">
                            Select the color for the sidebar, buttons, and headers.
                            <br><em>(Default: Navy Blue)</em>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>System Logo</label>
                                        <div class="current-logo">
                        <?php 
                        // FIX: Ginawang ../../ para tumama sa root folder
                        $logoUrl = '../../' . ltrim($settings['logo_path'], '/');
                        if (file_exists(realpath(__DIR__ . '/../../') . '/' . $settings['logo_path'])): 
                        ?>
                            <img src="<?= $logoUrl ?>" alt="Current Logo">
                        <?php else: ?>
                            <i class="fas fa-image" style="font-size:40px;color:#cbd5e1;margin-bottom:10px;"></i>
                        <?php endif; ?>
                        <p>Current Logo</p>
                    </div>
                    
                    <input type="file" name="logo" accept="image/jpeg,image/png,image/webp" style="margin-top:15px;">
                    <p style="font-size:12px; color:#64748b; margin-top:6px;"><i class="fas fa-info-circle"></i> Max 2MB. JPG, PNG, or WEBP.</p>
                </div>

                <div class="section-divider"><span>Security Settings</span></div>

                <div class="form-group">
                    <label>Master Recovery Key</label>
                    <div class="password-wrapper">
                        <input type="password" name="recovery_key" id="recoveryKey" value="<?= htmlspecialchars($settings['recovery_key'] ?? 'BSIT-ADMIN-2026') ?>" required>
                        <button type="button" class="toggle-eye" onclick="toggleRecoveryKey(this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <p style="font-size:12px; color:#64748b; margin-top:6px;">
                        <i class="fas fa-shield-alt"></i> This key is required if the Adviser forgets their password. Keep it secure.
                    </p>
                </div>

                <button type="submit" class="btn-save" style="margin-top: 10px;"><i class="fas fa-save"></i> Save Changes</button>
            </form>
        </div>
    </div>
</div>

<?php include '../../includes/shared_ui.php'; ?>

<script>
    function toggleRecoveryKey(btn) {
        const input = document.getElementById('recoveryKey');
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

    document.getElementById('userMenu').addEventListener('click',function(e){e.stopPropagation();this.classList.toggle('show');});
    window.addEventListener('click',function(){document.getElementById('userMenu').classList.remove('show');});
</script>
</body>
</html>