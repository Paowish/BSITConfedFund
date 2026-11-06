<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireAdviser();

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

$deniedFunds = $pdo->query("SELECT COUNT(*) FROM funds WHERE status='denied'")->fetchColumn();
$deniedExp = $pdo->query("SELECT COUNT(*) FROM expenses WHERE status='denied'")->fetchColumn();
$deniedTotal = $deniedFunds + $deniedExp;

$recentlyApproved = $pdo->query(
    "SELECT 'fund' AS type, id, amount, source AS detail, fund_date AS date, reviewed_at
     FROM funds WHERE status='approved'
     UNION ALL
     SELECT 'expense' AS type, id, amount, payee AS detail, expense_date AS date, reviewed_at
     FROM expenses WHERE status='approved'
     ORDER BY reviewed_at DESC
     LIMIT 5"
)->fetchAll();
?>

<!-- Summary Cards -->
<div class="summary-cards">
    <div class="summary-card">
        <h3><i class="fas fa-coins"></i> Total Funds</h3>
        <div class="amount">₱<?= number_format($summary['total_funds'], 2) ?></div>
    </div>
    <div class="summary-card">
        <h3><i class="fas fa-receipt"></i> Total Expenses</h3>
        <div class="amount">₱<?= number_format($summary['total_expenses'], 2) ?></div>
    </div>
    <div class="summary-card">
        <h3><i class="fas fa-balance-scale"></i> Balance</h3>
        <div class="amount">₱<?= number_format($summary['balance'], 2) ?></div>
    </div>
</div>

<!-- Status Counters -->
<div class="status-row">
    <div class="status-box">
        <div class="count" style="color:#e67e22;"><?= $pendingAudit ?></div>
        <div class="label"><i class="fas fa-clock"></i> Pending Audit</div>
    </div>
    <div class="status-box">
        <div class="count" style="color:#2980b9;"><?= $auditedTotal ?></div>
        <div class="label"><i class="fas fa-check-circle"></i> Audited (For You)</div>
    </div>
    <div class="status-box">
        <div class="count" style="color:#27ae60;"><?= $approvedTotal ?></div>
        <div class="label"><i class="fas fa-check"></i> Approved</div>
    </div>
    <div class="status-box">
        <div class="count" style="color:#e74c3c;"><?= $deniedTotal ?></div>
        <div class="label"><i class="fas fa-times-circle"></i> Denied</div>
    </div>
</div>

<!-- Action Card -->
<div class="card" style="display:flex; justify-content:space-between; align-items:center;">
    <div>
        <h3><?= $auditedTotal ?> item(s) waiting for your approval</h3>
        <p style="color:#666;">Items that have been audited and need your final decision.</p>
    </div>
    <a href="review.php" class="btn-primary"><i class="fas fa-arrow-right"></i> Review Now</a>
</div>

<!-- Recently Approved -->
<div class="card">
    <h3><i class="fas fa-history"></i> Recently Approved</h3>
    <?php if (count($recentlyApproved) == 0): ?>
        <p>No approved entries yet.</p>
    <?php else: ?>
        <table>
            <tr><th>Type</th><th>ID</th><th>Amount</th><th>Detail</th><th>Date</th><th>Approved At</th></tr>
            <?php foreach ($recentlyApproved as $r): ?>
            <tr>
                <td><?= $r['type'] === 'fund' ? 'Fund' : 'Expense' ?></td>
                <td><?= $r['id'] ?></td>
                <td>₱<?= number_format($r['amount'], 2) ?></td>
                <td><?= htmlspecialchars($r['detail']) ?></td>
                <td><?= $r['date'] ?></td>
                <td><?= $r['reviewed_at'] ? date('Y-m-d H:i', strtotime($r['reviewed_at'])) : '—' ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</div>