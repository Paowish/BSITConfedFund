<?php
// includes/sidebar.php
// Shared sidebar — detects role and shows appropriate menu

require_once __DIR__ . '/functions.php';

$base = '/bsit-fund-system-v2';

$isAdviser = isset($_SESSION['adviser_id']);
$isOfficer = isset($_SESSION['officer_id']);
$position = $_SESSION['officer_position'] ?? '';

// Fetch dynamic branding settings
$sysSettings = getSettings($pdo);
$sysLogo = $base . '/' . ltrim($sysSettings['logo_path'], '/');
$sysName = $sysSettings['org_short_name'] ?? 'BSIT Confed Fund';
?>

<!-- Backdrop for mobile -->
<div class="sidebar-backdrop" id="sidebarBackdrop" onclick="toggleSidebar()"></div>

<div class="sidebar" id="appSidebar">
    <div class="logo">
        <img src="<?= $sysLogo ?>" alt="Logo">
        <span><?= htmlspecialchars($sysName) ?></span>
    </div>
    <div class="nav-menu">

        <?php if ($isAdviser): ?>
            <a href="<?= $base ?>/admin/adviser/dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <a href="<?= $base ?>/admin/adviser/review.php"><i class="fas fa-check-circle"></i> Review</a>
            <a href="<?= $base ?>/admin/adviser/budget_approvals.php"><i class="fas fa-wallet"></i> Budget Approvals</a>
            <a href="<?= $base ?>/admin/adviser/final_approval.php"><i class="fas fa-file-signature"></i> Final Approval</a>
            <a href="<?= $base ?>/admin/adviser/inquiries.php"><i class="fas fa-comments"></i> Inquiries</a>
            <a href="<?= $base ?>/admin/adviser/manage_accounts.php"><i class="fas fa-users-cog"></i> Manage Accounts</a>
            <a href="<?= $base ?>/admin/adviser/config_approvals.php"><i class="fas fa-check-double"></i> Configuration Approvals</a>
            <a href="<?= $base ?>/admin/download_report.php"><i class="fas fa-file-pdf"></i> Reports</a>
            <a href="<?= $base ?>/admin/adviser/customization.php"><i class="fas fa-palette"></i> Customization</a>

        <?php elseif ($position === 'Treasurer'): ?>
            <a href="<?= $base ?>/admin/officer/dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <a href="<?= $base ?>/admin/officer/submit_fund.php"><i class="fas fa-coins"></i> Submit Fund</a>
            <a href="<?= $base ?>/admin/officer/submit_expense.php"><i class="fas fa-receipt"></i> Submit Expense</a>
            <a href="<?= $base ?>/admin/officer/review_budgets.php"><i class="fas fa-wallet"></i> Budget Review</a>
            <a href="<?= $base ?>/admin/officer/release_funds.php"><i class="fas fa-hand-holding-usd"></i> Fund Release</a>
            <a href="<?= $base ?>/admin/officer/complete_event.php"><i class="fas fa-flag-checkered"></i> Complete Event</a>
            <a href="<?= $base ?>/admin/officer/submit_liquidation.php"><i class="fas fa-file-invoice-dollar"></i> Submit Liquidation</a>
            <a href="<?= $base ?>/admin/officer/view_message_board.php"><i class="fas fa-envelope"></i> View Message Board</a>
            <a href="<?= $base ?>/admin/download_report.php"><i class="fas fa-file-pdf"></i> Reports</a>

        <?php elseif ($position === 'Auditor'): ?>
            <a href="<?= $base ?>/admin/auditor/dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <a href="<?= $base ?>/admin/auditor/review.php"><i class="fas fa-clipboard-check"></i> Audit Queue</a>
            <a href="<?= $base ?>/admin/auditor/liquidation_audit.php"><i class="fas fa-file-invoice-dollar"></i> Liquidation Audit</a>
            <a href="<?= $base ?>/admin/download_report.php"><i class="fas fa-file-pdf"></i> Reports</a>

        <?php elseif ($position === 'Mayor'): ?>
            <a href="<?= $base ?>/admin/officer/dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <a href="<?= $base ?>/admin/officer/manage_categories.php"><i class="fas fa-tags"></i> Manage Categories</a>
            <a href="<?= $base ?>/admin/officer/manage_events.php"><i class="fas fa-calendar-alt"></i> Manage Events</a>
            <a href="<?= $base ?>/admin/officer/manage_school_years.php"><i class="fas fa-calendar-plus"></i> Manage School Years</a>
            <a href="<?= $base ?>/admin/officer/view_message_board.php"><i class="fas fa-envelope"></i> View Message Board</a>
            <a href="<?= $base ?>/admin/download_report.php"><i class="fas fa-file-pdf"></i> Reports</a>

        <?php elseif ($position === 'Vice Mayor'): ?>
            <a href="<?= $base ?>/admin/officer/dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <a href="<?= $base ?>/admin/officer/view_message_board.php"><i class="fas fa-envelope"></i> View Message Board</a>
            <a href="<?= $base ?>/admin/download_report.php"><i class="fas fa-file-pdf"></i> Reports</a>

        <?php elseif ($position === 'Business Manager'): ?>
            <a href="<?= $base ?>/admin/officer/dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <a href="<?= $base ?>/admin/officer/view_message_board.php"><i class="fas fa-envelope"></i> View Message Board</a>
            <a href="<?= $base ?>/admin/download_report.php"><i class="fas fa-file-pdf"></i> Reports</a>

        <?php endif; ?>

        <a href="<?= $base ?>/public/index.php"><i class="fas fa-globe"></i> Public Page</a>

    </div>
</div>