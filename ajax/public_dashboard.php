<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

$selectedSchoolYear = $_GET['school_year'] ?? '';
$selectedFundSource = $_GET['fund_source'] ?? '';

$fundWhere = "WHERE f.status = 'approved'";
$expWhere = "WHERE e.status = 'approved'";
$fundParams = [];
$expParams = [];

if (!empty($selectedSchoolYear)) {
    $fundWhere .= " AND f.school_year = ?";
    $expWhere .= " AND e.school_year = ?";
    $fundParams[] = $selectedSchoolYear;
    $expParams[] = $selectedSchoolYear;
}
if (!empty($selectedFundSource)) {
    $expWhere .= " AND e.fund_source = ?";
    $expParams[] = $selectedFundSource;
}

$fundSQL = "SELECT f.* FROM funds f $fundWhere ORDER BY f.fund_date DESC";
$fundStmt = $pdo->prepare($fundSQL);
$fundStmt->execute($fundParams);
$approvedFunds = $fundStmt->fetchAll();

$expSQL = "SELECT e.*, c.name AS category_name
           FROM expenses e
           JOIN categories c ON e.category_id = c.id
           $expWhere
           ORDER BY e.expense_date DESC";
$expStmt = $pdo->prepare($expSQL);
$expStmt->execute($expParams);
$approvedExpenses = $expStmt->fetchAll();

$totalFundsFiltered = array_sum(array_column($approvedFunds, 'amount'));
$totalExpensesFiltered = array_sum(array_column($approvedExpenses, 'amount'));

$pieLabels = [];
$pieData = [];
if (count($approvedExpenses) > 0) {
    $catTotals = [];
    foreach ($approvedExpenses as $e) {
        $cat = $e['category_name'] ?? 'Uncategorized';
        $catTotals[$cat] = ($catTotals[$cat] ?? 0) + $e['amount'];
    }
    $pieLabels = array_keys($catTotals);
    $pieData = array_values($catTotals);
}

$fundSourceSummary = [];
foreach ($approvedExpenses as $e) {
    $src = $e['fund_source'] ?: 'Unspecified';
    $fundSourceSummary[$src] = ($fundSourceSummary[$src] ?? 0) + $e['amount'];
}
arsort($fundSourceSummary);

// ---------- Approved Budgets with line items ----------
$budgets = $pdo->query(
    "SELECT eb.*, e.name AS event_name
     FROM event_budgets eb
     LEFT JOIN events e ON eb.event_id = e.id
     WHERE eb.status = 'approved'
     ORDER BY eb.created_at DESC"
)->fetchAll();

$budgetActuals = [];
$budgetExpenses = $pdo->query(
    "SELECT budget_id, SUM(amount) AS total
     FROM expenses
     WHERE status='approved' AND budget_id IS NOT NULL
     GROUP BY budget_id"
)->fetchAll();
foreach ($budgetExpenses as $row) {
    $budgetActuals[$row['budget_id']] = $row['total'];
}

// Fetch line items for all approved budgets
$budgetItems = [];
$itemsStmt = $pdo->query(
    "SELECT bpi.*, bpi.budget_id
     FROM budget_plan_items bpi
     JOIN event_budgets eb ON bpi.budget_id = eb.id
     WHERE eb.status = 'approved'"
)->fetchAll();
foreach ($itemsStmt as $item) {
    $budgetItems[$item['budget_id']][] = $item;
}
?>

<!-- Summary Cards (same as before) -->
<div class="summary-cards">
    <div class="summary-card">
        <h3><i class="fas fa-coins"></i> Total Funds</h3>
        <div class="amount">₱<?= number_format($totalFundsFiltered, 2) ?></div>
    </div>
    <div class="summary-card">
        <h3><i class="fas fa-receipt"></i> Total Expenses</h3>
        <div class="amount">₱<?= number_format($totalExpensesFiltered, 2) ?></div>
    </div>
    <div class="summary-card">
        <h3><i class="fas fa-balance-scale"></i> Balance</h3>
        <div class="amount">₱<?= number_format($totalFundsFiltered - $totalExpensesFiltered, 2) ?></div>
    </div>
</div>

<!-- Charts -->
<div class="chart-row">
    <?php if (count($pieLabels) > 0): ?>
    <div class="chart-box">
        <h3>Expenses by Category</h3>
        <canvas id="pieChart" height="250"
                data-labels='<?= json_encode($pieLabels) ?>'
                data-values='<?= json_encode($pieData) ?>'></canvas>
    </div>
    <?php endif; ?>
    <div class="chart-box">
        <h3>Funds vs Expenses<?= $selectedSchoolYear ? ' (' . htmlspecialchars($selectedSchoolYear) . ')' : '' ?></h3>
        <canvas id="barChart" height="250"
                data-funds="<?= $totalFundsFiltered ?>"
                data-expenses="<?= $totalExpensesFiltered ?>"></canvas>
    </div>
</div>

<!-- Budget Plans with Line Items -->
<?php if (count($budgets) > 0): ?>
<div class="card">
    <h2><i class="fas fa-wallet"></i> Approved Budget Plans & Actual Comparison</h2>
    <table>
        <tr>
            <th>Budget Name</th>
            <th>Event</th>
            <th>School Year</th>
            <th>Allocated</th>
            <th>Actual</th>
            <th>Variance</th>
            <th>Details</th>
        </tr>
        <?php foreach ($budgets as $b):
            $allocated = $b['allocated_amount'];
            $actual = $budgetActuals[$b['id']] ?? 0;
            $variance = $allocated - $actual;
            $varianceText = $variance >= 0 ? '₱' . number_format($variance,2) . ' Under' : '₱' . number_format(abs($variance),2) . ' Over';
        ?>
        <tr>
            <td><?= htmlspecialchars($b['budget_name']) ?></td>
            <td><?= htmlspecialchars($b['event_name'] ?? '—') ?></td>
            <td><?= htmlspecialchars($b['school_year'] ?? '—') ?></td>
            <td>₱<?= number_format($allocated, 2) ?></td>
            <td>₱<?= number_format($actual, 2) ?></td>
            <td><?= $varianceText ?></td>
            <td>
                <?php if (!empty($budgetItems[$b['id']])): ?>
                    <details>
                        <summary style="cursor:pointer;">View Line Items</summary>
                        <table>
                            <tr><th>Item</th><th>Qty</th><th>Unit Cost</th><th>Estimated Total</th></tr>
                            <?php foreach ($budgetItems[$b['id']] as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['item_name']) ?></td>
                                <td><?= $item['quantity'] ?></td>
                                <td>₱<?= number_format($item['unit_cost'], 2) ?></td>
                                <td>₱<?= number_format($item['estimated_total'], 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </table>
                    </details>
                <?php else: ?>
                    <span>No line items</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
<?php endif; ?>

<!-- Summary by Fund Source (same as before) -->
<?php if (count($fundSourceSummary) > 0): ?>
<div class="card">
    <h2><i class="fas fa-search-dollar"></i> Summary by Fund Source</h2>
    <table>
        <tr><th>Fund Source</th><th>Total Spent</th></tr>
        <?php foreach ($fundSourceSummary as $src => $total): ?>
        <tr><td><?= htmlspecialchars($src) ?></td><td>₱<?= number_format($total, 2) ?></td></tr>
        <?php endforeach; ?>
    </table>
</div>
<?php endif; ?>

<!-- Approved Funds Table (same as before) -->
<div class="card">
    <h2><i class="fas fa-coins"></i> Approved Funds</h2>
    <?php if (count($approvedFunds) === 0): ?>
        <p>No funds found for the selected filters.</p>
    <?php else: ?>
        <table>
            <tr><th>Date</th><th>Source</th><th>Amount</th><th>School Year</th><th>Receipt</th></tr>
            <?php foreach ($approvedFunds as $f): ?>
            <tr>
                <td><?= htmlspecialchars($f['fund_date']) ?></td>
                <td><?= htmlspecialchars($f['source']) ?></td>
                <td>₱<?= number_format($f['amount'], 2) ?></td>
                <td><?= htmlspecialchars($f['school_year'] ?? '—') ?></td>
                <td>
                    <?php if (!empty($f['receipt_path'])): ?>
                        <?php foreach (explode(',', $f['receipt_path']) as $rec): ?>
                            <a href="../<?= htmlspecialchars(trim($rec)) ?>" target="_blank"><i class="fas fa-file-alt"></i></a>
                        <?php endforeach; ?>
                    <?php else: ?>—<?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</div>

<!-- Approved Expenses Table (same as before) -->
<div class="card">
    <h2><i class="fas fa-receipt"></i> Approved Expenses</h2>
    <?php if (count($approvedExpenses) === 0): ?>
        <p>No expenses found for the selected filters.</p>
    <?php else: ?>
        <table>
            <tr><th>Date</th><th>Category</th><th>Item</th><th>Amount</th><th>Fund Source</th><th>School Year</th><th>Event</th><th>Receipt</th></tr>
            <?php foreach ($approvedExpenses as $e): ?>
            <tr>
                <td><?= htmlspecialchars($e['expense_date']) ?></td>
                <td><?= htmlspecialchars($e['category_name']) ?></td>
                <td><?= htmlspecialchars($e['payee']) ?></td>
                <td>₱<?= number_format($e['amount'], 2) ?></td>
                <td><?= htmlspecialchars($e['fund_source'] ?? '—') ?></td>
                <td><?= htmlspecialchars($e['school_year'] ?? '—') ?></td>
                <td><?= htmlspecialchars($e['event'] ?? '—') ?></td>
                <td>
                    <?php if (!empty($e['receipt_path'])): ?>
                        <?php foreach (explode(',', $e['receipt_path']) as $rec): ?>
                            <a href="../<?= htmlspecialchars(trim($rec)) ?>" target="_blank"><i class="fas fa-file-alt"></i></a>
                        <?php endforeach; ?>
                    <?php else: ?>—<?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</div>