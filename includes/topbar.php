<?php
// includes/topbar.php
// Shared top-bar component with dynamic avatar and color theme

require_once __DIR__ . '/functions.php';

if (!isset($basePath)) $basePath = '../../';

// Determine current user's name
if (isset($_SESSION['adviser_id'])) {
    $currentUserName = $_SESSION['adviser_name'] ?? 'User';
} elseif (isset($_SESSION['officer_id'])) {
    $currentUserName = $_SESSION['officer_name'] ?? 'User';
} else {
    $currentUserName = 'User';
}

// Get profile picture and system settings
global $pdo;
$currentPic = null;
$sysSettings = [];
if (isset($pdo)) {
    $currentPic = getCurrentUserProfilePic($pdo);
    $sysSettings = getSettings($pdo);
}

$themeColor = $sysSettings['primary_color'] ?? '#1a3c5e';
?>
<style>
    :root {
        --theme-primary: <?= htmlspecialchars($themeColor) ?>;
    }
    /* Sidebar override */
    .sidebar {
        background-color: var(--theme-primary) !important;
    }
    .sidebar .logo {
        border-bottom: 1px solid rgba(255,255,255,0.15) !important;
    }
    /* Buttons */
    .btn-primary, .btn-submit, .btn-add, .btn-login, .vmodal-btn-primary {
        background-color: var(--theme-primary) !important;
    }
    /* Text colors */
    .top-bar-left h1, .card h3, .modal-box h3, .vmodal-box h3 {
        color: var(--theme-primary) !important;
    }
    /* Tabs */
    .tab-button.active {
        background-color: var(--theme-primary) !important;
        border-color: var(--theme-primary) !important;
    }
    /* Quick actions */
    .quick-actions a {
        background-color: var(--theme-primary) !important;
    }
    /* Pagination */
    .pag-btn.active {
        background-color: var(--theme-primary) !important;
        border-color: var(--theme-primary) !important;
    }
    /* Public headers (if present) */
    .public-header {
        background-color: var(--theme-primary) !important;
    }
    
    /* Force Dropdown Fix */
    .user-menu .dropdown {
        min-width: 200px !important;
    }
    .user-menu .dropdown a {
        white-space: nowrap !important;
    }

    /* ===== HAMBURGER MENU & MOBILE RESPONSIVENESS ===== */
    .hamburger-btn {
        display: none;
        background: none;
        border: none;
        font-size: 22px;
        color: #1e293b;
        cursor: pointer;
        margin-right: 15px;
        padding: 5px;
    }

    .sidebar-backdrop {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.5);
        z-index: 999;
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    @media (max-width: 768px) {
        .hamburger-btn {
            display: block;
        }
        .top-bar {
            padding: 0 15px;
        }
        .top-bar-left {
            display: flex;
            align-items: center;
        }
        .top-bar-left h1 {
            font-size: 16px;
        }
        .top-bar-left .welcome {
            font-size: 11px;
        }
        
        /* Hide sidebar by default on mobile */
        .sidebar {
            transform: translateX(-100%);
            transition: transform 0.3s ease;
            z-index: 1000 !important;
        }
        .sidebar.open {
            transform: translateX(0);
        }
        
        /* Show backdrop when sidebar is open */
        .sidebar-backdrop.show {
            display: block;
            opacity: 1;
        }
        
        /* Remove margin from main area on mobile */
        .main-area {
            margin-left: 0 !important;
        }
        
        /* Make tables scrollable */
        .card {
            padding: 15px;
            overflow-x: auto;
        }
        table {
            min-width: 600px; /* Force horizontal scroll for wide tables */
        }
    }
</style>

<div class="top-bar">
    <div class="top-bar-left">
        <button class="hamburger-btn" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
        <div>
            <h1><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></h1>
            <?php if (!empty($pageSubtitle)): ?>
                <p class="welcome"><?= $pageSubtitle ?></p>
            <?php endif; ?>
        </div>
    </div>
    <div class="user-menu" id="userMenu">
        <?= renderAvatar($currentUserName, $currentPic, 38) ?>
        <div class="dropdown" id="userDropdown">
            <a href="<?= $basePath ?>profile.php"><i class="fas fa-user-circle"></i> My Profile</a>
            <a href="<?= $basePath ?>change_password.php"><i class="fas fa-key"></i> Change Password</a>
            <a href="<?= $basePath ?>logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>
</div>

<script>
    // Hamburger Menu Toggle
    function toggleSidebar() {
        document.getElementById('appSidebar').classList.toggle('open');
        document.getElementById('sidebarBackdrop').classList.toggle('show');
    }
</script>