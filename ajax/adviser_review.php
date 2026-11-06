<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireAdviser();

$auditedFunds = $pdo->query(
    "SELECT f.*, o.name AS officer_name, o.position 
     FROM funds f 
     JOIN officers o ON f.officer_id = o.id 
     WHERE f.status = 'audited' 
     ORDER BY f.created_at ASC"
)->fetchAll();

$auditedExpenses = $pdo->query(
    "SELECT e.*, c.name AS category_name, o.name AS officer_name, o.position 
     FROM expenses e 
     JOIN categories c ON e.category_id = c.id 
     JOIN officers o ON e.officer_id = o.id 
     WHERE e.status = 'audited' 
     ORDER BY e.created_at ASC"
)->fetchAll();
?>

<?php if (count($auditedFunds) == 0): ?>
    <p>No fund entries awaiting your approval.</p>
<?php else: ?>
    <table>
        <tr><th>ID</th><th>Officer</th><th>Date</th><th>Source</th><th>Amount</th><th>Receipt</th><th>Action</th></tr>
        <?php foreach ($auditedFunds as $f): ?>
        <tr>
            <td><?= $f['id'] ?></td>
            <td><?= htmlspecialchars($f['officer_name']) ?> (<?= $f['position'] ?>)</td>
            <td><?= $f['fund_date'] ?></td>
            <td><?= htmlspecialchars($f['source']) ?></td>
            <td>₱<?= number_format($f['amount'], 2) ?></td>
            <td>
                <?php if (!empty($f['receipt_path'])): ?>
                    <?php foreach (explode(',', $f['receipt_path']) as $rec): ?>
                        <a href="../<?= trim($rec) ?>" target="_blank"><i class="fas fa-file-alt"></i></a>
                    <?php endforeach; ?>
                <?php else: ?>—<?php endif; ?>
            </td>
            <td>
                <form method="POST" class="inline" onsubmit="return confirm('Approve this fund?')">
                    <input type="hidden" name="type" value="fund">
                    <input type="hidden" name="id" value="<?= $f['id'] ?>">
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="comment" value="">
                    <button class="btn-approve">✅ Approve</button>
                </form>
                <form method="POST" class="inline" onsubmit="return confirm('Deny this fund?')">
                    <input type="hidden" name="type" value="fund">
                    <input type="hidden" name="id" value="<?= $f['id'] ?>">
                    <input type="hidden" name="action" value="deny">
                    <input type="text" name="comment" placeholder="Reason" required>
                    <button class="btn-deny">❌ Deny</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>

<!--SPLIT-->

<?php if (count($auditedExpenses) == 0): ?>
    <p>No expense entries awaiting your approval.</p>
<?php else: ?>
    <table>
        <tr><th>ID</th><th>Officer</th><th>Date</th><th>Category</th><th>Item</th><th>Amount</th><th>Receipt</th><th>Action</th></tr>
        <?php foreach ($auditedExpenses as $e): ?>
        <tr>
            <td><?= $e['id'] ?></td>
            <td><?= htmlspecialchars($e['officer_name']) ?> (<?= $e['position'] ?>)</td>
            <td><?= $e['expense_date'] ?></td>
            <td><?= htmlspecialchars($e['category_name']) ?></td>
            <td><?= htmlspecialchars($e['payee']) ?></td>
            <td>₱<?= number_format($e['amount'], 2) ?></td>
            <td>
                <?php if (!empty($e['receipt_path'])): ?>
                    <?php foreach (explode(',', $e['receipt_path']) as $rec): ?>
                        <a href="../<?= trim($rec) ?>" target="_blank"><i class="fas fa-file-alt"></i></a>
                    <?php endforeach; ?>
                <?php else: ?>—<?php endif; ?>
            </td>
            <td>
                <form method="POST" class="inline" onsubmit="return confirm('Approve this expense?')">
                    <input type="hidden" name="type" value="expense">
                    <input type="hidden" name="id" value="<?= $e['id'] ?>">
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="comment" value="">
                    <button class="btn-approve">✅ Approve</button>
                </form>
                <form method="POST" class="inline" onsubmit="return confirm('Deny this expense?')">
                    <input type="hidden" name="type" value="expense">
                    <input type="hidden" name="id" value="<?= $e['id'] ?>">
                    <input type="hidden" name="action" value="deny">
                    <input type="text" name="comment" placeholder="Reason" required>
                    <button class="btn-deny">❌ Deny</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>