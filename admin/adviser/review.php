<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
requireAdviser();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'];
    $id = $_POST['id'];
    $action = $_POST['action'];
    $comment = trim($_POST['comment'] ?? '');

    if ($action === 'deny' && empty($comment)) {
        $error = "A reason/comment is required when denying an entry.";
    } else {
        $table = ($type === 'fund') ? 'funds' : 'expenses';
        $status = ($action === 'approve') ? 'approved' : 'denied';

        $stmt = $pdo->prepare(
            "UPDATE $table 
             SET status = ?, reviewed_by = ?, reviewed_at = NOW(), review_comment = ?
             WHERE id = ?"
        );
        $stmt->execute([$status, $_SESSION['adviser_id'], $comment, $id]);
        $message = ucfirst($type) . " #$id has been " . $status . ".";
    }
}

// Fetch audited funds
$auditedFunds = $pdo->query(
    "SELECT f.*, o.name AS officer_name, o.position 
     FROM funds f 
     JOIN officers o ON f.officer_id = o.id 
     WHERE f.status = 'audited' 
     ORDER BY f.created_at ASC"
)->fetchAll();

// Fetch audited expenses
$auditedExpenses = $pdo->query(
    "SELECT e.*, c.name AS category_name, o.name AS officer_name, o.position 
     FROM expenses e 
     JOIN categories c ON e.category_id = c.id 
     JOIN officers o ON e.officer_id = o.id 
     WHERE e.status = 'audited' 
     ORDER BY e.created_at ASC"
)->fetchAll();

// Helper to build documents array
function buildDocs($receipt_path) {
    $docs = [];
    if (!empty($receipt_path)) {
        foreach (explode(',', $receipt_path) as $rec) {
            $rec = trim($rec);
            if ($rec === '') continue;
            $ext = strtolower(pathinfo($rec, PATHINFO_EXTENSION));
            $isImage = in_array($ext, ['jpg','jpeg','png','gif']);
            $docs[] = [
                'name' => basename($rec),
                'type' => $isImage ? 'image' : 'pdf',
                'url'  => '../../' . $rec
            ];
        }
    }
    return $docs;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Adviser Review - BSIT Fund System</title>
    <link rel="stylesheet" href="../../assets/css/adviser.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        form.inline { display:inline; }
        .success { color:#27ae60; } .error { color:#e74c3c; }
    </style>
</head>
<body>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/bsit-fund-system-v2/includes/sidebar.php'; ?>

<div class="main-area">
   <?php 
$pageTitle = 'REVIEW';
$pageSubtitle = ' <strong>' . htmlspecialchars($_SESSION['officer_name'] ?? $_SESSION['adviser_name'] ?? 'User') . '</strong>';
$basePath = '../';
include $_SERVER['DOCUMENT_ROOT'] . '/bsit-fund-system-v2/includes/topbar.php'; 
?>

    <div class="page-content">
        <?php if ($message): ?><p class="success"><?= htmlspecialchars($message) ?></p><?php endif; ?>
        <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>

        <div class="card">
            <h3>Audited Funds (Awaiting Final Approval)</h3>
            <?php if (count($auditedFunds) == 0): ?>
                <p>No fund entries awaiting your approval.</p>
            <?php else: ?>
                <table>
                    <tr><th>ID</th><th>Officer</th><th>Date</th><th>Source</th><th>Amount</th><th>Receipt</th><th>Action</th></tr>
                    <?php foreach ($auditedFunds as $f):
                        $docs = buildDocs($f['receipt_path']);
                        $viewData = [
                            'type' => 'Fund',
                            'status' => 'Audited — Awaiting Final Approval',
                            'submitted_by' => $f['officer_name'] . ' (' . $f['position'] . ')',
                            'source' => $f['source'],
                            'amount' => number_format($f['amount'], 2),
                            'date' => $f['fund_date'],
                            'school_year' => $f['school_year'] ?? '—',
                            'description' => $f['description'] ?? '—',
                            'documents' => $docs
                        ];
                    ?>
                    <tr>
                        <td><?=$f['id']?></td>
                        <td><?=htmlspecialchars($f['officer_name'])?> (<?=$f['position']?>)</td>
                        <td><?=$f['fund_date']?></td>
                        <td><?=htmlspecialchars($f['source'])?></td>
                        <td>₱<?=number_format($f['amount'],2)?></td>
                        <td>
                            <?php if(!empty($f['receipt_path'])): foreach(explode(',',$f['receipt_path']) as $rec): ?>
                                <a href="../../<?=trim($rec)?>" target="_blank"><i class="fas fa-file-alt"></i></a>
                            <?php endforeach; else: ?>—<?php endif; ?>
                        </td>
                        <td>
                            <button type="button" class="btn-view" onclick='openViewModal(<?= htmlspecialchars(json_encode($viewData), ENT_QUOTES) ?>)'><i class="fas fa-eye"></i> View</button>
                            <button type="button" class="btn-approve" onclick='openApproveModal("fund", <?= $f["id"] ?>, "Fund #<?= $f["id"] ?> (₱<?= number_format($f["amount"],2) ?>)" )'><i class="fas fa-check"></i> Approve</button>
                            <button type="button" class="btn-deny" onclick='openDenyModal("fund", <?= $f["id"] ?>, "Fund #<?= $f["id"] ?> (₱<?= number_format($f["amount"],2) ?>)" )'><i class="fas fa-times"></i> Deny</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3>Audited Expenses (Awaiting Final Approval)</h3>
            <?php if (count($auditedExpenses) == 0): ?>
                <p>No expense entries awaiting your approval.</p>
            <?php else: ?>
                <table>
                    <tr><th>ID</th><th>Officer</th><th>Date</th><th>Category</th><th>Item</th><th>Amount</th><th>Receipt</th><th>Action</th></tr>
                    <?php foreach ($auditedExpenses as $e):
                        $docs = buildDocs($e['receipt_path']);
                        $viewData = [
                            'type' => 'Expense',
                            'status' => 'Audited — Awaiting Final Approval',
                            'submitted_by' => $e['officer_name'] . ' (' . $e['position'] . ')',
                            'category' => $e['category_name'],
                            'payee' => $e['payee'],
                            'amount' => number_format($e['amount'], 2),
                            'date' => $e['expense_date'],
                            'school_year' => $e['school_year'] ?? '—',
                            'fund_source' => $e['fund_source'] ?? '—',
                            'event' => $e['event'] ?? '—',
                            'description' => $e['description'] ?? '—',
                            'documents' => $docs
                        ];
                    ?>
                    <tr>
                        <td><?=$e['id']?></td>
                        <td><?=htmlspecialchars($e['officer_name'])?> (<?=$e['position']?>)</td>
                        <td><?=$e['expense_date']?></td>
                        <td><?=htmlspecialchars($e['category_name'])?></td>
                        <td><?=htmlspecialchars($e['payee'])?></td>
                        <td>₱<?=number_format($e['amount'],2)?></td>
                        <td>
                            <?php if(!empty($e['receipt_path'])): foreach(explode(',',$e['receipt_path']) as $rec): ?>
                                <a href="../../<?=trim($rec)?>" target="_blank"><i class="fas fa-file-alt"></i></a>
                            <?php endforeach; else: ?>—<?php endif; ?>
                        </td>
                        <td>
                            <button type="button" class="btn-view" onclick='openViewModal(<?= htmlspecialchars(json_encode($viewData), ENT_QUOTES) ?>)'><i class="fas fa-eye"></i> View</button>
                            <button type="button" class="btn-approve" onclick='openApproveModal("expense", <?= $e["id"] ?>, "Expense #<?= $e["id"] ?> (₱<?= number_format($e["amount"],2) ?>)" )'><i class="fas fa-check"></i> Approve</button>
                            <button type="button" class="btn-deny" onclick='openDenyModal("expense", <?= $e["id"] ?>, "Expense #<?= $e["id"] ?> (₱<?= number_format($e["amount"],2) ?>)" )'><i class="fas fa-times"></i> Deny</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../../includes/shared_ui.php'; ?>

<script>
    document.getElementById('userMenu').addEventListener('click',function(e){e.stopPropagation();this.classList.toggle('show');});
    window.addEventListener('click',function(){document.getElementById('userMenu').classList.remove('show');});
</script>
</body>
</html>