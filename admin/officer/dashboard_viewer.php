<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireOfficer();

$summary = getFinancialSummary($pdo);

$pendingAuditFunds = $pdo->query("SELECT COUNT(*) FROM funds WHERE status='pending_audit'")->fetchColumn();
$pendingAuditExp = $pdo->query("SELECT COUNT(*) FROM expenses WHERE status='pending_audit'")->fetchColumn();
$pendingAudit = $pendingAuditFunds + $pendingAuditExp;

$auditedFunds = $pdo->query("SELECT COUNT(*) FROM funds WHERE status='audited'")->fetchColumn();
$auditedExp = $pdo->query("SELECT COUNT(*) FROM expenses WHERE status='audited'")->fetchColumn();
$auditedTotal = $auditedFunds + $auditedExp;

$approvedFundsCount = $pdo->query("SELECT COUNT(*) FROM funds WHERE status='approved'")->fetchColumn();
$approvedExpCount = $pdo->query("SELECT COUNT(*) FROM expenses WHERE status='approved'")->fetchColumn();
$approvedTotal = $approvedFundsCount + $approvedExpCount;

$currentPic = getCurrentUserProfilePic($pdo);

$approvedFunds = $pdo->query("SELECT f.*, o.name AS officer_name, o.position FROM funds f JOIN officers o ON f.officer_id=o.id WHERE f.status='approved' ORDER BY f.created_at DESC LIMIT 5")->fetchAll();
$approvedExpenses = $pdo->query("SELECT e.*, c.name AS category_name, o.name AS officer_name, o.position FROM expenses e JOIN categories c ON e.category_id=c.id JOIN officers o ON e.officer_id=o.id WHERE e.status='approved' ORDER BY e.created_at DESC LIMIT 5")->fetchAll();

$pendingBudgets = $pdo->query("SELECT COUNT(*) FROM event_budgets WHERE status='pending_treasurer_review'")->fetchColumn();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Officer Dashboard - BSIT Fund System</title>
    <link rel="stylesheet" href="../../assets/css/officer.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Arial, sans-serif; }
        body { background: #f4f6f9; }
        .main-area { margin-left: 240px; }
        .content { padding: 25px; }

        .summary-cards { display: flex; gap: 20px; margin-bottom: 25px; flex-wrap: wrap; }
        .summary-card {
            flex: 1; min-width: 180px; border-radius: 12px; padding: 20px;
            color: white; box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            display: flex; align-items: center; gap: 15px;
        }
        .summary-card .icon {
            font-size: 30px; background: rgba(255,255,255,0.2);
            width: 60px; height: 60px; display: flex; align-items: center; justify-content: center; border-radius: 50%;
        }
        .summary-card .details { color: #ffffff !important; }
        .summary-card .details h4 {
            font-size: 14px; font-weight: 400; color: #ffffff !important;
            opacity: 0.95; margin-bottom: 5px; text-transform: uppercase; letter-spacing: 0.5px;
        }
        .summary-card .details .amount {
            font-size: 28px; font-weight: 700; color: #ffffff !important;
            text-shadow: 0 1px 2px rgba(0,0,0,0.15);
        }
        .funds { background: linear-gradient(135deg, #2980b9, #1a3c5e); }
        .expenses { background: linear-gradient(135deg, #ff6b6b, #ee5a24); }
        .balance { background: linear-gradient(135deg, #00b894, #009432); }

        .dash-section { display: block; margin-bottom: 25px; }
        .dash-section > .two-col:last-child { margin-bottom: 0; }

        .stat-badges { display: flex; gap: 10px; margin-bottom: 25px; flex-wrap: wrap; }
        .stat-badge {
            background: #fff; border: 1px solid #e2e8f0; border-radius: 50px;
            padding: 8px 16px; display: flex; align-items: center; gap: 6px;
            font-weight: 600; font-size: 14px; box-shadow: 0 2px 4px rgba(0,0,0,0.03);
        }
        .stat-badge .stat-icon { font-size: 16px; }
        .stat-badge .stat-number { font-size: 16px; font-weight: bold; color: #1a3c5e; }
        .stat-badge .stat-label { text-transform: uppercase; font-size: 12px; color: #777; }
        .stat-badge.pending .stat-icon, .stat-badge.pending .stat-number { color: #e67e22; }
        .stat-badge.audited .stat-icon, .stat-badge.audited .stat-number { color: #2980b9; }
        .stat-badge.approved .stat-icon, .stat-badge.approved .stat-number { color: #009432; }

        .quick-actions { display: flex; gap: 15px; margin-bottom: 25px; flex-wrap: wrap; }
        .quick-actions a {
            background: #1a3c5e; color: white; padding: 12px 20px; border-radius: 8px;
            text-decoration: none; font-weight: 500; display: flex; align-items: center; gap: 8px;
            transition: transform 0.1s, background 0.2s;
        }
        .quick-actions a:hover { background: #2c5282; transform: translateY(-2px); }

        .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 25px; }
        @media (max-width: 900px) { .two-col { grid-template-columns: 1fr; } }

        .card {
            background: white; border-radius: 12px; border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03); padding: 20px;
        }
        .card h3 { color: #1a3c5e; margin-bottom: 15px; display: flex; align-items: center; gap: 8px; }

        table { width: 100%; border-collapse: collapse; }
        th {
            background: #f8f9fa; color: #555; text-align: left; padding: 10px 12px;
            font-size: 13px; text-transform: uppercase; border-bottom: 1px solid #e2e8f0;
        }
        td { padding: 10px 12px; border-bottom: 1px solid #f0f0f0; }
        tr:hover { background: #f1f5f9; }
        .amount { text-align: right; }
    </style>
</head>
<body>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/bsit-fund-system-v2/includes/sidebar.php'; ?>

<div class="main-area">
    <?php 
    $pageTitle = 'DASHBOARD';
    $pageSubtitle = 'Welcome, <strong>' . htmlspecialchars($_SESSION['officer_name']) . '</strong> (' . htmlspecialchars($_SESSION['officer_position']) . ')';
    $basePath = '../';
    include $_SERVER['DOCUMENT_ROOT'] . '/bsit-fund-system-v2/includes/topbar.php'; 
    ?>

    <div class="content">
        <div class="summary-cards">
            <div class="summary-card funds">
                <div class="icon"><i class="fas fa-coins"></i></div>
                <div class="details">
                    <h4>Total Funds</h4>
                    <div class="amount">₱<?= number_format($summary['total_funds'], 2) ?></div>
                </div>
            </div>
            <div class="summary-card expenses">
                <div class="icon"><i class="fas fa-receipt"></i></div>
                <div class="details">
                    <h4>Total Expenses</h4>
                    <div class="amount">₱<?= number_format($summary['total_expenses'], 2) ?></div>
                </div>
            </div>
            <div class="summary-card balance">
                <div class="icon"><i class="fas fa-balance-scale"></i></div>
                <div class="details">
                    <h4>Balance</h4>
                    <div class="amount">₱<?= number_format($summary['balance'], 2) ?></div>
                </div>
            </div>
        </div>


        <div id="overview" class="dash-section">
            <div class="stat-badges">
                <div class="stat-badge pending">
                    <i class="fas fa-clock stat-icon"></i>
                    <span class="stat-number"><?= $pendingAudit ?></span>
                    <span class="stat-label">Pending Audit</span>
                </div>
                <div class="stat-badge audited">
                    <i class="fas fa-check-circle stat-icon"></i>
                    <span class="stat-number"><?= $auditedTotal ?></span>
                    <span class="stat-label">Audited</span>
                </div>
                <div class="stat-badge approved">
                    <i class="fas fa-check stat-icon"></i>
                    <span class="stat-number"><?= $approvedTotal ?></span>
                    <span class="stat-label">Approved</span>
                </div>
            </div>

            <div class="quick-actions">
                <a href="view_message_board.php"><i class="fas fa-envelope"></i> Message Board</a>
                <?php if ($_SESSION['officer_position'] === 'Mayor'): ?>
                    <a href="manage_events.php"><i class="fas fa-calendar-plus"></i> Add Event</a>
                <?php endif; ?>
            </div>
        </div>

        <div id="funds" class="dash-section">
            <div class="card">
                <h3>Approved Funds</h3>
                <table>
                    <tr><th>Date</th><th>Source</th><th class="amount">Amount</th><th>Receipt</th></tr>
                    <?php foreach ($approvedFunds as $f): ?>
                    <tr>
                        <td><?= date('Y-m-d', strtotime($f['created_at'])) ?></td>
                        <td><?= htmlspecialchars($f['source']) ?></td>
                        <td class="amount">₱<?= number_format($f['amount'], 2) ?></td>
                        <td><?php if (!empty($f['receipt_path'])): foreach(explode(',', $f['receipt_path']) as $rec): ?><a href="../../<?=trim($rec)?>" target="_blank"><i class="fas fa-file-alt"></i></a><?php endforeach; else: ?>—<?php endif; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>

        <div id="expenses" class="dash-section">
            <div class="card">
                <h3>Approved Expenses</h3>
                <table>
                    <tr><th>Date</th><th>Category</th><th>Item</th><th class="amount">Amount</th><th>Receipt</th></tr>
                    <?php foreach ($approvedExpenses as $e): ?>
                    <tr>
                        <td><?= date('Y-m-d', strtotime($e['created_at'])) ?></td>
                        <td><?= htmlspecialchars($e['category_name']) ?></td>
                        <td><?= htmlspecialchars($e['payee']) ?></td>
                        <td class="amount">₱<?= number_format($e['amount'], 2) ?></td>
                        <td><?php if (!empty($e['receipt_path'])): foreach(explode(',', $e['receipt_path']) as $rec): ?><a href="../../<?=trim($rec)?>" target="_blank"><i class="fas fa-file-alt"></i></a><?php endforeach; else: ?>—<?php endif; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>

        <div id="budgets" class="dash-section">
            <div class="card">
                <h3>Budget Summary</h3>
                <p>Pending Budget Plans: <?= $pendingBudgets ?></p>
                <a href="view_message_board.php" class="btn-sm" style="background:#1a3c5e;color:white;padding:8px 12px;border-radius:4px;text-decoration:none;">Go to Message Board</a>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/shared_ui.php'; ?>

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