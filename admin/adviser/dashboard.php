<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireAdviser();

$summary = getFinancialSummary($pdo);
$currentPic = getCurrentUserProfilePic($pdo);

// Status counts
$pendingAuditFunds = $pdo->query("SELECT COUNT(*) FROM funds WHERE status='pending_audit'")->fetchColumn();
$pendingAuditExp = $pdo->query("SELECT COUNT(*) FROM expenses WHERE status='pending_audit'")->fetchColumn();
$pendingAudit = $pendingAuditFunds + $pendingAuditExp;

$auditedFunds = $pdo->query("SELECT COUNT(*) FROM funds WHERE status='audited'")->fetchColumn();
$auditedExp = $pdo->query("SELECT COUNT(*) FROM expenses WHERE status='audited'")->fetchColumn();
$auditedTotal = $auditedFunds + $auditedExp;

$approvedFundsCount = $pdo->query("SELECT COUNT(*) FROM funds WHERE status='approved'")->fetchColumn();
$approvedExpCount = $pdo->query("SELECT COUNT(*) FROM expenses WHERE status='approved'")->fetchColumn();
$approvedTotal = $approvedFundsCount + $approvedExpCount;

$deniedFunds = $pdo->query("SELECT COUNT(*) FROM funds WHERE status='denied'")->fetchColumn();
$deniedExp = $pdo->query("SELECT COUNT(*) FROM expenses WHERE status='denied'")->fetchColumn();
$deniedTotal = $deniedFunds + $deniedExp;

// Budget plans pending adviser review
$pendingBudgetApprovals = $pdo->query("SELECT COUNT(*) FROM event_budgets WHERE status='pending_adviser_review'")->fetchColumn();

// Recent approved entries
$recentlyApproved = $pdo->query(
    "SELECT 'fund' AS type, id, amount, source AS detail, fund_date AS date, reviewed_at
     FROM funds WHERE status='approved'
     UNION ALL
     SELECT 'expense' AS type, id, amount, payee AS detail, expense_date AS date, reviewed_at
     FROM expenses WHERE status='approved'
     ORDER BY reviewed_at DESC
     LIMIT 5"
)->fetchAll();

// Pending adviser review (audited items)
$auditedFundsList = $pdo->query("SELECT f.*, o.name AS officer_name, o.position FROM funds f JOIN officers o ON f.officer_id=o.id WHERE f.status='audited' ORDER BY f.created_at ASC")->fetchAll();
$auditedExpensesList = $pdo->query("SELECT e.*, c.name AS category_name, o.name AS officer_name, o.position FROM expenses e JOIN categories c ON e.category_id=c.id JOIN officers o ON e.officer_id=o.id WHERE e.status='audited' ORDER BY e.created_at ASC")->fetchAll();

// Pending configuration approvals counts
$pendingCategories = $pdo->query("SELECT COUNT(*) FROM categories WHERE status='pending'")->fetchColumn();
$pendingEvents = $pdo->query("SELECT COUNT(*) FROM events WHERE status='pending'")->fetchColumn();
$pendingBudgets = $pdo->query("SELECT COUNT(*) FROM event_budgets WHERE status='pending_treasurer_review'")->fetchColumn();
$configPendingTotal = $pendingCategories + $pendingEvents + $pendingBudgets;

// ============ TRANSACTIONS QUERY ============
$stmtTx = $pdo->prepare(
    "SELECT 'Fund' AS type, id, source AS details, amount, fund_date AS tx_date, status, created_at
     FROM funds WHERE status IN ('audited', 'approved', 'denied')
     UNION ALL
     SELECT 'Expense' AS type, id, payee AS details, amount, expense_date AS tx_date, status, created_at
     FROM expenses WHERE status IN ('audited', 'approved', 'denied')
     UNION ALL
     SELECT 'Budget Plan' AS type, id, budget_name AS details, allocated_amount AS amount, created_at AS tx_date, status, created_at
     FROM event_budgets WHERE status IN ('pending_adviser_review', 'approved', 'adviser_rejected')
     UNION ALL
     SELECT 'Liquidation' AS type, id, CONCAT('Event ID: ', event_id) AS details, total_expenses AS amount, created_at AS tx_date, status, created_at
     FROM liquidations WHERE status IN ('audited_compliant', 'audited_with_findings', 'closed')
     ORDER BY created_at DESC"
);
$stmtTx->execute();
$allTransactions = $stmtTx->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Adviser Dashboard - BSIT Fund System</title>
    <link rel="stylesheet" href="../../assets/css/adviser.css">
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
        .stat-badge.denied .stat-icon, .stat-badge.denied .stat-number { color: #ee5a24; }
        .stat-badge.budget-pending .stat-icon, .stat-badge.budget-pending .stat-number { color: #8e44ad; }

        .quick-actions { display: flex; gap: 15px; margin-bottom: 25px; flex-wrap: wrap; }
        .quick-actions a {
            background: #1a3c5e; color: white; padding: 12px 20px; border-radius: 8px;
            text-decoration: none; font-weight: 500; display: flex; align-items: center; gap: 8px;
            transition: transform 0.1s, background 0.2s;
        }
        .quick-actions a:hover { background: #2c5282; transform: translateY(-2px); }
        .quick-actions a.budget-btn { background: #8e44ad; }
        .quick-actions a.budget-btn:hover { background: #6c3483; }

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

        .btn-sm {
            background: #2980b9; color: white; padding: 4px 10px;
            border-radius: 4px; text-decoration: none; font-size: 12px;
        }
        .btn-sm:hover { background: #1c6ea4; }
    </style>
</head>
<body>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/bsit-fund-system-v2/includes/sidebar.php'; ?>

<div class="main-area">
    <?php 
    $pageTitle = 'DASHBOARD';
    $pageSubtitle = 'Welcome, <strong>' . htmlspecialchars($_SESSION['adviser_name']) . '</strong>';
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


        <!-- OVERVIEW -->
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
                    <span class="stat-label">For Review</span>
                </div>
                <div class="stat-badge approved">
                    <i class="fas fa-check stat-icon"></i>
                    <span class="stat-number"><?= $approvedTotal ?></span>
                    <span class="stat-label">Approved</span>
                </div>
                <div class="stat-badge denied">
                    <i class="fas fa-times-circle stat-icon"></i>
                    <span class="stat-number"><?= $deniedTotal ?></span>
                    <span class="stat-label">Denied</span>
                </div>
                <div class="stat-badge budget-pending">
                    <i class="fas fa-wallet stat-icon"></i>
                    <span class="stat-number"><?= $pendingBudgetApprovals ?></span>
                    <span class="stat-label">Budget Approval</span>
                </div>
            </div>

            <div class="quick-actions">
                <a href="review.php"><i class="fas fa-check-circle"></i> Review Items</a>
                <a href="budget_approvals.php" class="budget-btn"><i class="fas fa-wallet"></i> Budget Approvals</a>
                <a href="config_approvals.php"><i class="fas fa-check-double"></i> Config Approvals</a>
                <a href="manage_accounts.php"><i class="fas fa-users-cog"></i> Manage Accounts</a>
            </div>
        </div>

        <!-- REVIEW -->
        <div id="review" class="dash-section">
            <div class="two-col">
                <div class="card">
                    <h3>Funds Awaiting Your Approval</h3>
                    <table>
                        <tr><th>ID</th><th>Source</th><th class="amount">Amount</th><th>Action</th></tr>
                        <?php foreach ($auditedFundsList as $f): ?>
                        <tr>
                            <td><?= $f['id'] ?></td>
                            <td><?= htmlspecialchars($f['source']) ?></td>
                            <td class="amount">₱<?= number_format($f['amount'], 2) ?></td>
                            <td><a href="review.php" class="btn-sm">Review</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
                <div class="card">
                    <h3>Expenses Awaiting Your Approval</h3>
                    <table>
                        <tr><th>ID</th><th>Item</th><th class="amount">Amount</th><th>Action</th></tr>
                        <?php foreach ($auditedExpensesList as $e): ?>
                        <tr>
                            <td><?= $e['id'] ?></td>
                            <td><?= htmlspecialchars($e['payee']) ?></td>
                            <td class="amount">₱<?= number_format($e['amount'], 2) ?></td>
                            <td><a href="review.php" class="btn-sm">Review</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>
        </div>

        <!-- CONFIG -->
        <div id="config" class="dash-section">
            <div class="card">
                <h3><i class="fas fa-check-double"></i> Pending Config Approvals</h3>
                <p>Total pending: <strong><?= $configPendingTotal ?></strong></p>
                <ul>
                    <li>Categories: <?= $pendingCategories ?></li>
                    <li>Events: <?= $pendingEvents ?></li>
                    <li>Budgets: <?= $pendingBudgets ?></li>
                </ul>
                <a href="config_approvals.php" class="btn-sm">Go to Approvals</a>
            </div>
        </div>

        <!-- RECENTLY APPROVED -->
        <div id="recent" class="dash-section">
            <div class="card">
                <h3><i class="fas fa-history"></i> Recently Approved</h3>
                <table>
                    <tr><th>Type</th><th>ID</th><th>Detail</th><th class="amount">Amount</th><th>Date</th></tr>
                    <?php foreach ($recentlyApproved as $r): ?>
                    <tr>
                        <td><?= $r['type'] === 'fund' ? 'Fund' : 'Expense' ?></td>
                        <td><?= $r['id'] ?></td>
                        <td><?= htmlspecialchars($r['detail']) ?></td>
                        <td class="amount">₱<?= number_format($r['amount'], 2) ?></td>
                        <td><?= $r['date'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>

        <!-- TRANSACTIONS -->
        <div id="transactions" class="dash-section">
            <div class="card">
                <h3><i class="fas fa-exchange-alt"></i> Transaction Tracking</h3>
                <p style="color: #64748b; margin-bottom: 20px; font-size: 14px;">Monitor the real-time status of all funds, expenses, budgets, and liquidations across the system.</p>
                <?php if (count($allTransactions) == 0): ?>
                    <p>No transactions found.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>ID</th>
                                <th>Details</th>
                                <th class="amount">Amount</th>
                                <th>Current Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allTransactions as $tx): ?>
                            <tr>
                                <td><?= date('Y-m-d', strtotime($tx['tx_date'])) ?></td>
                                <td><strong><?= $tx['type'] ?></strong></td>
                                <td>#<?= $tx['id'] ?></td>
                                <td><?= htmlspecialchars($tx['details']) ?></td>
                                <td class="amount">₱<?= number_format($tx['amount'], 2) ?></td>
                                <td><?= getStatusBadge($tx['status']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
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