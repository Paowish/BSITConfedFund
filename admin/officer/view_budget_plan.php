<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireOfficer();

if ($_SESSION['officer_position'] !== 'Business Manager') {
    header('Location: dashboard.php');
    exit;
}

$budgetId = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("SELECT * FROM event_budgets WHERE id = ? AND created_by = ?");
$stmt->execute([$budgetId, $_SESSION['officer_id']]);
$budget = $stmt->fetch();

if (!$budget) {
    die("Budget plan not found.");
}

$eventStmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
$eventStmt->execute([$budget['event_id']]);
$event = $eventStmt->fetch();

$itemsStmt = $pdo->prepare("SELECT * FROM budget_plan_items WHERE budget_id = ?");
$itemsStmt->execute([$budgetId]);
$items = $itemsStmt->fetchAll();

$currentPic = getCurrentUserProfilePic($pdo);
?>
<!DOCTYPE html>
<html>
<head>
    <title>View Budget Plan - BSIT Fund System</title>
    <link rel="stylesheet" href="../../assets/css/officer.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .card { background:#fff; border-radius:10px; border:1px solid #e2e8f0; box-shadow:0 2px 8px rgba(0,0,0,0.03); padding:25px 30px; margin-bottom:25px; }
        .card h3 { margin-top:0; color:#1a3c5e; }
        table { width:100%; border-collapse:collapse; }
        th, td { padding:10px; text-align:left; border-bottom:1px solid #eee; }
    </style>
</head>
<body>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/bsit-fund-system-v2/includes/sidebar.php'; ?>

<div class="main-area">
    <?php 
    $pageTitle = 'View Budget Plan';
    $pageSubtitle = 'Welcome, <strong>' . htmlspecialchars($_SESSION['officer_name']) . '</strong>';
    $basePath = '../';
    include $_SERVER['DOCUMENT_ROOT'] . '/bsit-fund-system-v2/includes/topbar.php'; 
    ?>

    <div class="page-content">
        <div class="card">
            <h3>Event: <?= htmlspecialchars($event['name']) ?></h3>
            <p><strong>School Year:</strong> <?= htmlspecialchars($event['school_year'] ?? '—') ?></p>
            <p><strong>Status:</strong> <?= ucwords(str_replace('_', ' ', $budget['status'])) ?></p>
            <p><strong>Total Estimated Cost:</strong> ₱<?= number_format($budget['allocated_amount'], 2) ?></p>
        </div>

        <div class="card">
            <h3>Line Items</h3>
            <table>
                <tr><th>Item</th><th>Quantity</th><th>Unit Cost</th><th>Estimated Total</th></tr>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= htmlspecialchars($item['item_name']) ?></td>
                    <td><?= $item['quantity'] ?></td>
                    <td>₱<?= number_format($item['unit_cost'], 2) ?></td>
                    <td>₱<?= number_format($item['estimated_total'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <div class="card">
            <h3>Notes</h3>
            <p><?= nl2br(htmlspecialchars($budget['notes'] ?? '—')) ?></p>
        </div>

        <div class="card">
            <h3>Attachments</h3>
            <?php if (!empty($budget['attachments'])): ?>
                <ul>
                    <?php foreach (explode(',', $budget['attachments']) as $att): ?>
                        <li><a href="../../<?= trim($att) ?>" target="_blank"><i class="fas fa-file"></i> View</a></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p>No attachments.</p>
            <?php endif; ?>
        </div>

        <a href="dashboard.php" class="btn-primary" style="padding:10px 20px; background:#1a3c5e; color:white; border-radius:6px; text-decoration:none;">Back to Dashboard</a>
    </div>
</div>

<?php include '../../includes/shared_ui.php'; ?>

<script>
    document.getElementById('userMenu').addEventListener('click',function(e){e.stopPropagation();this.classList.toggle('show');});
    window.addEventListener('click',function(){document.getElementById('userMenu').classList.remove('show');});
</script>
</body>
</html>