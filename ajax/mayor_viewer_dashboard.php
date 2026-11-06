<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireOfficer();

$summary = getFinancialSummary($pdo);

$approvedFunds = $pdo->query("SELECT f.*, o.name AS officer_name, o.position FROM funds f JOIN officers o ON f.officer_id=o.id WHERE f.status='approved' ORDER BY f.created_at DESC")->fetchAll();
$approvedExpenses = $pdo->query("SELECT e.*, c.name AS category_name, o.name AS officer_name, o.position FROM expenses e JOIN categories c ON e.category_id=c.id JOIN officers o ON e.officer_id=o.id WHERE e.status='approved' ORDER BY e.created_at DESC")->fetchAll();
$pendingFunds = $pdo->query("SELECT f.*, o.name AS officer_name, o.position FROM funds f JOIN officers o ON f.officer_id=o.id WHERE f.status='pending_audit' ORDER BY f.created_at DESC")->fetchAll();
$pendingExpenses = $pdo->query("SELECT e.*, c.name AS category_name, o.name AS officer_name, o.position FROM expenses e JOIN categories c ON e.category_id=c.id JOIN officers o ON e.officer_id=o.id WHERE e.status='pending_audit' ORDER BY e.created_at DESC")->fetchAll();
?>

<!-- Summary Cards -->
<div class="summary-cards">
    <div class="summary-card"><h3>Total Funds</h3><div class="amount">₱<?= number_format($summary['total_funds'],2) ?></div></div>
    <div class="summary-card"><h3>Total Expenses</h3><div class="amount">₱<?= number_format($summary['total_expenses'],2) ?></div></div>
    <div class="summary-card"><h3>Balance</h3><div class="amount">₱<?= number_format($summary['balance'],2) ?></div></div>
</div>

<!-- Approved Funds -->
<div class="card">
    <h3>Approved Funds</h3>
    <?php if(count($approvedFunds)==0): ?><p>No approved funds yet.</p>
    <?php else: ?><table><tr><th>Date</th><th>Source</th><th>Amount</th><th>Receipt</th></tr>
    <?php foreach($approvedFunds as $f): ?>
        <tr><td><?= date('Y-m-d', strtotime($f['created_at'])) ?></td><td><?= htmlspecialchars($f['source']) ?></td><td>₱<?= number_format($f['amount'],2) ?></td><td><?php if(!empty($f['receipt_path'])): foreach(explode(',',$f['receipt_path']) as $rec): ?><a href="../../<?=trim($rec)?>" target="_blank"><i class="fas fa-file-alt"></i></a><?php endforeach; else: ?>—<?php endif; ?></td></tr>
    <?php endforeach; ?></table><?php endif; ?>
</div>

<!-- Approved Expenses -->
<div class="card">
    <h3>Approved Expenses</h3>
    <?php if(count($approvedExpenses)==0): ?><p>No approved expenses yet.</p>
    <?php else: ?><table><tr><th>Date</th><th>Category</th><th>Who Paid</th><th>Amount</th><th>Receipt</th></tr>
    <?php foreach($approvedExpenses as $e): ?>
        <tr><td><?= date('Y-m-d', strtotime($e['created_at'])) ?></td><td><?= htmlspecialchars($e['category_name']) ?></td><td><?= htmlspecialchars($e['payee']) ?></td><td>₱<?= number_format($e['amount'],2) ?></td><td><?php if(!empty($e['receipt_path'])): foreach(explode(',',$e['receipt_path']) as $rec): ?><a href="../../<?=trim($rec)?>" target="_blank"><i class="fas fa-file-alt"></i></a><?php endforeach; else: ?>—<?php endif; ?></td></tr>
    <?php endforeach; ?></table><?php endif; ?>
</div>

<!-- Pending Funds -->
<div class="card">
    <h3>Pending Funds (Audit)</h3>
    <?php if(count($pendingFunds)==0): ?><p>No pending funds.</p>
    <?php else: ?><table><tr><th>Date</th><th>Source</th><th>Amount</th><th>Receipt</th></tr>
    <?php foreach($pendingFunds as $f): ?><tr><td><?= date('Y-m-d', strtotime($f['created_at'])) ?></td><td><?= htmlspecialchars($f['source']) ?></td><td>₱<?= number_format($f['amount'],2) ?></td><td><?php if(!empty($f['receipt_path'])): foreach(explode(',',$f['receipt_path']) as $rec): ?><a href="../../<?=trim($rec)?>" target="_blank"><i class="fas fa-file-alt"></i></a><?php endforeach; else: ?>—<?php endif; ?></td></tr><?php endforeach; ?></table><?php endif; ?>
</div>

<!-- Pending Expenses -->
<div class="card">
    <h3>Pending Expenses (Audit)</h3>
    <?php if(count($pendingExpenses)==0): ?><p>No pending expenses.</p>
    <?php else: ?><table><tr><th>Date</th><th>Category</th><th>Who Paid</th><th>Amount</th><th>Receipt</th></tr>
    <?php foreach($pendingExpenses as $e): ?><tr><td><?= date('Y-m-d', strtotime($e['created_at'])) ?></td><td><?= htmlspecialchars($e['category_name']) ?></td><td><?= htmlspecialchars($e['payee']) ?></td><td>₱<?= number_format($e['amount'],2) ?></td><td><?php if(!empty($e['receipt_path'])): foreach(explode(',',$e['receipt_path']) as $rec): ?><a href="../../<?=trim($rec)?>" target="_blank"><i class="fas fa-file-alt"></i></a><?php endforeach; else: ?>—<?php endif; ?></td></tr><?php endforeach; ?></table><?php endif; ?>
</div>