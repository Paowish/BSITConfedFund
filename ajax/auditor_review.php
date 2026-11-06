<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireAuditor();

// Fetch pending_audit funds
$pendingFunds = $pdo->query(
    "SELECT f.*, o.name AS officer_name, o.position 
     FROM funds f 
     JOIN officers o ON f.officer_id = o.id 
     WHERE f.status = 'pending_audit' 
     ORDER BY f.created_at ASC"
)->fetchAll();

// Fetch pending_audit expenses
$pendingExpenses = $pdo->query(
    "SELECT e.*, c.name AS category_name, o.name AS officer_name, o.position 
     FROM expenses e 
     JOIN categories c ON e.category_id = c.id 
     JOIN officers o ON e.officer_id = o.id 
     WHERE e.status = 'pending_audit' 
     ORDER BY e.created_at ASC"
)->fetchAll();

// Output funds table
if (count($pendingFunds) == 0): ?>
    <p>No funds waiting.</p>
<?php else: ?>
    <table>
        <tr><th>ID</th><th>Officer</th><th>Date</th><th>Source</th><th>Amount</th><th>Receipt</th><th>Action</th></tr>
        <?php foreach($pendingFunds as $f): ?>
        <tr>
            <td><?= $f['id'] ?></td>
            <td><?= htmlspecialchars($f['officer_name']) ?> (<?= $f['position'] ?>)</td>
            <td><?= $f['fund_date'] ?></td>
            <td><?= htmlspecialchars($f['source']) ?></td>
            <td>₱<?= number_format($f['amount'],2) ?></td>
            <td>
                <?php if(!empty($f['receipt_path'])): foreach(explode(',',$f['receipt_path']) as $rec): ?>
                    <a href="../<?= trim($rec) ?>" target="_blank"><i class="fas fa-file-alt"></i></a>
                <?php endforeach; else: ?>—<?php endif; ?>
            </td>
            <td>
                <form method="POST" class="inline">
                    <input type="hidden" name="type" value="fund">
                    <input type="hidden" name="id" value="<?= $f['id'] ?>">
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="comment" value="">
                    <button class="btn-approve" onclick="return confirm('Approve this fund?')">✅ Audit Approve</button>
                </form>
                <form method="POST" class="inline">
                    <input type="hidden" name="type" value="fund">
                    <input type="hidden" name="id" value="<?= $f['id'] ?>">
                    <input type="hidden" name="action" value="deny">
                    <input type="text" name="comment" placeholder="Reason" required>
                    <button class="btn-deny" onclick="return confirm('Deny this fund?')">❌ Deny</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>

<!--SPLIT-->

<?php if (count($pendingExpenses) == 0): ?>
    <p>No expenses waiting.</p>
<?php else: ?>
    <table>
        <tr><th>ID</th><th>Officer</th><th>Date</th><th>Category</th><th>Item</th><th>Amount</th><th>Receipt</th><th>Action</th></tr>
        <?php foreach($pendingExpenses as $e): ?>
        <tr>
            <td><?= $e['id'] ?></td>
            <td><?= htmlspecialchars($e['officer_name']) ?> (<?= $e['position'] ?>)</td>
            <td><?= $e['expense_date'] ?></td>
            <td><?= htmlspecialchars($e['category_name']) ?></td>
            <td><?= htmlspecialchars($e['payee']) ?></td>
            <td>₱<?= number_format($e['amount'],2) ?></td>
            <td>
                <?php if(!empty($e['receipt_path'])): foreach(explode(',',$e['receipt_path']) as $rec): ?>
                    <a href="../<?= trim($rec) ?>" target="_blank"><i class="fas fa-file-alt"></i></a>
                <?php endforeach; else: ?>—<?php endif; ?>
            </td>
            <td>
                <form method="POST" class="inline">
                    <input type="hidden" name="type" value="expense">
                    <input type="hidden" name="id" value="<?= $e['id'] ?>">
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="comment" value="">
                    <button class="btn-approve" onclick="return confirm('Approve this expense?')">✅ Audit Approve</button>
                </form>
                <form method="POST" class="inline">
                    <input type="hidden" name="type" value="expense">
                    <input type="hidden" name="id" value="<?= $e['id'] ?>">
                    <input type="hidden" name="action" value="deny">
                    <input type="text" name="comment" placeholder="Reason" required>
                    <button class="btn-deny" onclick="return confirm('Deny this expense?')">❌ Deny</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>