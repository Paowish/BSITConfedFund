<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireOfficer();

if ($_SESSION['officer_position'] !== 'Treasurer') {
    header('Location: dashboard.php');
    exit;
}

$message = '';
$error = '';
$warnings = [];
$currentPic = getCurrentUserProfilePic($pdo);

$events = $pdo->query(
    "SELECT e.*, 
            eb.id AS budget_id, 
            eb.allocated_amount,
            COALESCE((
                SELECT SUM(amount) 
                FROM expenses 
                WHERE event_id = e.id AND status = 'approved'
            ), 0) AS total_expenses
     FROM events e
     LEFT JOIN event_budgets eb ON eb.event_id = e.id AND eb.status = 'approved'
     LEFT JOIN liquidations l ON l.event_id = e.id
     WHERE e.status = 'event_completed' AND l.id IS NULL
     ORDER BY e.created_at DESC"
)->fetchAll();

$eventExpenses = [];
if (count($events) > 0) {
    $eventIds = array_column($events, 'id');
    $placeholders = implode(',', array_fill(0, count($eventIds), '?'));
    $expStmt = $pdo->prepare(
        "SELECT e.id AS expense_id, e.event_id, e.expense_date, e.payee, e.amount, e.status,
                c.name AS category_name
         FROM expenses e
         LEFT JOIN categories c ON e.category_id = c.id
         WHERE e.event_id IN ($placeholders) AND e.status IN ('approved', 'pending_audit', 'audited')
         ORDER BY e.expense_date ASC"
    );
    $expStmt->execute($eventIds);
    foreach ($expStmt as $exp) {
        $eventExpenses[$exp['event_id']][] = [
            'id' => $exp['expense_id'],
            'date' => $exp['expense_date'],
            'category' => $exp['category_name'] ?? 'Uncategorized',
            'payee' => $exp['payee'],
            'amount' => (float)$exp['amount'],
            'status' => $exp['status']
        ];
    }
}

$categories = $pdo->query("SELECT id, name FROM categories WHERE status='approved' ORDER BY name")->fetchAll();
$fundSources = [
    'Sponsorship Income','Donation Income','Fundraising Income','Merchandise Sales Income',
    'University Financial Assistance','Officer Contribution','Cash Advance from Organization Fund',
    'Previous Event Excess Funds','Savings/Reserve Fund','School Reimbursement Expense/Reimbursement',
    'Income Generating Projects (IGPs)'
];
$schoolYears = $pdo->query("SELECT year_label FROM school_years ORDER BY year_label DESC")->fetchAll(PDO::FETCH_COLUMN);
$officers = $pdo->query("SELECT id, name, position FROM officers WHERE is_active = 1 ORDER BY name")->fetchAll();
$budgets = $pdo->query("SELECT id, budget_name, event_id FROM event_budgets WHERE status='approved' ORDER BY budget_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['liquidation_submit'])) {
    $eventId = $_POST['event_id'] ?? 0;
    $budgetId = $_POST['budget_id'] ?? null;
    $notes = trim($_POST['notes'] ?? '');

    if (empty($eventId)) {
        $error = "Please select an event.";
    } elseif (empty($_FILES['attachments']['name'][0])) {
        $error = "Please upload at least one source document.";
    } else {
        $totalBudget = 0;
        if ($budgetId) {
            $stmt = $pdo->prepare("SELECT allocated_amount FROM event_budgets WHERE id = ?");
            $stmt->execute([$budgetId]);
            $totalBudget = $stmt->fetchColumn() ?: 0;
        }

        $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE event_id = ? AND status = 'approved'");
        $stmt->execute([$eventId]);
        $totalExpenses = $stmt->fetchColumn();

        $variance = $totalBudget - $totalExpenses;

        if ($variance < 0 && empty($notes)) {
            $error = "Notes are required because this liquidation is over budget.";
        }

        if (empty($error) && $totalExpenses == 0 && empty($notes)) {
            $error = "No approved expenses found for this event. Notes are required.";
        }

        if (empty($error)) {
            $attachmentPaths = [];
            $uploadDir = realpath(__DIR__ . '/../../') . '/uploads/liquidations/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
            foreach ($_FILES['attachments']['name'] as $i => $name) {
                $fileError = $_FILES['attachments']['error'][$i];
                if ($fileError === UPLOAD_ERR_OK) {
                    $fileTmp = $_FILES['attachments']['tmp_name'][$i];
                    $unique = time() . '_' . $i . '_' . basename($name);
                    $target = $uploadDir . $unique;
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mime = $finfo->file($fileTmp);
                    if (in_array($mime, $allowedTypes)) {
                        if (move_uploaded_file($fileTmp, $target)) {
                            $attachmentPaths[] = 'uploads/liquidations/' . $unique;
                        }
                    }
                }
            }
            $attachmentsStr = !empty($attachmentPaths) ? implode(',', $attachmentPaths) : null;

            $stmt = $pdo->prepare(
                "INSERT INTO liquidations (event_id, budget_id, submitted_by, total_budget, total_expenses, variance, notes, attachments, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending_audit')"
            );
            $stmt->execute([$eventId, $budgetId ?: null, $_SESSION['officer_id'], $totalBudget, $totalExpenses, $variance, $notes, $attachmentsStr]);

            $pdo->prepare("UPDATE events SET status = 'pending_audit' WHERE id = ?")->execute([$eventId]);

            $message = "Liquidation submitted. Event is now Pending Audit.";
            header("Location: submit_liquidation.php?msg=" . urlencode($message));
            exit;
        }
    }
}

if (isset($_GET['msg'])) $message = $_GET['msg'];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Submit Liquidation - BSIT Fund System</title>
    <link rel="stylesheet" href="../../assets/css/officer.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .card { background:#fff; border-radius:10px; border:1px solid #e2e8f0; box-shadow:0 2px 8px rgba(0,0,0,0.03); padding:25px 30px; margin-bottom:25px; }
        .card h3 { margin-top:0; color:#1a3c5e; }
        table { width:100%; border-collapse:collapse; }
        th, td { padding:10px; text-align:left; border-bottom:1px solid #eee; }
        th { background:#f8f9fa; text-transform:uppercase; font-size:13px; color:#555; }
        form label { display:block; font-weight:600; margin-bottom:6px; }
        form input, form select, form textarea { width:100%; padding:10px; margin-bottom:15px; border:1px solid #d1d5db; border-radius:6px; box-sizing:border-box; }
        .success { color:#27ae60; } .error { color:#e74c3c; }
        .modal-overlay { display:none; position:fixed; top:0;left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center; padding:20px; }
        .modal-overlay.active { display:flex; }
        .modal-overlay.nested { z-index:10001; background:rgba(0,0,0,0.65); }
        .modal-box { background:#fff; border-radius:12px; width:680px; max-width:95%; max-height:88vh; overflow-y:auto; padding:30px; position:relative; }
        .modal-box.nested-box { width:640px; }
        .modal-box h3 { margin-top:0; color:#1a3c5e; }
        .modal-close { position:absolute; top:15px; right:20px; background:none; border:none; font-size:22px; cursor:pointer; }

        .liq-preview-box { background: #f8fafc; border: 1px solid #e2e8f0; border-left: 4px solid #2980b9; border-radius: 8px; padding: 15px 18px; margin-bottom: 18px; }
        .liq-preview-box h4 { margin: 0 0 12px 0; font-size: 12px; color: #1a3c5e; text-transform: uppercase; letter-spacing: 0.5px; display: flex; align-items: center; gap: 8px; }
        .liq-row { display: flex; justify-content: space-between; align-items: center; padding: 7px 0; font-size: 13.5px; color: #334155; border-bottom: 1px dashed #e2e8f0; }
        .liq-row:last-child { border-bottom: none; }
        .liq-row.liq-total { font-weight: 700; color: #1a3c5e; border-top: 2px solid #1a3c5e; border-bottom: none; padding-top: 10px; margin-top: 4px; font-size: 15px; }
        .liq-row.liq-total strong { font-size: 16px; }

        .liq-warning { background: #fef3c7; border-left: 3px solid #f39c12; padding: 10px 14px; border-radius: 6px; font-size: 12.5px; color: #856404; margin-top: 12px; display: flex; align-items: flex-start; gap: 8px; }
        .liq-warning i { color: #f39c12; font-size: 14px; margin-top: 1px; }
        .liq-warning.danger { background: #fdecea; border-left-color: #c0392b; color: #721c24; }
        .liq-warning.danger i { color: #c0392b; }

        .liq-expenses-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px 18px; margin-bottom: 18px; max-height: 240px; overflow-y: auto; }
        .liq-expenses-box h4 { margin: 0 0 12px 0; font-size: 12px; color: #1a3c5e; text-transform: uppercase; letter-spacing: 0.5px; display: flex; align-items: center; justify-content: space-between; }
        .liq-expenses-box h4 .count-badge { background: #2980b9; color: white; padding: 2px 10px; border-radius: 50px; font-size: 11px; font-weight: 700; }
        .liq-exp-table { width: 100%; border-collapse: collapse; font-size: 12.5px; }
        .liq-exp-table th { background: #eef2f7; color: #555; padding: 6px 8px; text-align: left; font-size: 11px; text-transform: uppercase; border-bottom: 1px solid #e2e8f0; }
        .liq-exp-table td { padding: 6px 8px; border-bottom: 1px solid #f0f0f0; color: #333; }
        .liq-exp-table td.amount { text-align: right; font-weight: 600; color: #1a3c5e; }

        .status-pill { display: inline-block; padding: 2px 8px; border-radius: 50px; font-size: 10px; font-weight: 700; text-transform: uppercase; }
        .status-pill.approved { background: #d4edda; color: #155724; }
        .status-pill.pending { background: #fff3cd; color: #856404; }
        .status-pill.audited { background: #cce5ff; color: #004085; }

        .btn-add-expense { background: #e8f0f8; color: #1a3c5e; border: 1px dashed #2980b9; padding: 9px 16px; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 13px; display: inline-flex; align-items: center; gap: 6px; transition: background 0.2s; margin-top: 10px; }
        .btn-add-expense:hover { background: #d8e6f2; }

        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .form-row > div { min-width: 0; }
        .modal-box label { display:block; font-weight:600; margin-bottom:6px; font-size: 13px; }
        .modal-box input, .modal-box select, .modal-box textarea { width:100%; padding:10px; margin-bottom:14px; border:1px solid #d1d5db; border-radius:6px; box-sizing:border-box; font-size: 13.5px; }
        .modal-box input:focus, .modal-box select:focus, .modal-box textarea:focus { outline: none; border-color: #1a3c5e; box-shadow: 0 0 0 3px rgba(26,60,94,0.1); }
        .modal-box textarea { resize: vertical; min-height: 60px; }

        .btn-row { display: flex; gap: 10px; justify-content: flex-end; margin-top: 10px; }
        .btn-primary { background: #1a3c5e; color: #fff; border: none; padding: 11px 24px; border-radius: 6px; cursor: pointer; font-size: 13.5px; font-weight: 600; }
        .btn-primary:hover { background: #2c5282; }
        .btn-primary:disabled { background: #95a5a6; cursor: not-allowed; }
        .btn-secondary { background: #fff; color: #64748b; border: 1px solid #d1d5db; padding: 11px 20px; border-radius: 6px; cursor: pointer; font-size: 13.5px; font-weight: 600; }
        .btn-secondary:hover { background: #f1f5f9; }

        .file-preview { font-size: 12px; color: #666; margin-top: -8px; margin-bottom: 12px; }

        .toast-success { position: fixed; top: 20px; right: 20px; background: #27ae60; color: #fff; padding: 14px 22px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); font-weight: 600; font-size: 13.5px; z-index: 99999; opacity: 0; transform: translateY(-20px); transition: all 0.3s; display: flex; align-items: center; gap: 10px; }
        .toast-success.show { opacity: 1; transform: translateY(0); }
        .toast-error { background: #e74c3c; }
    </style>
</head>
<body>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/bsit-fund-system-v2/includes/sidebar.php'; ?>

<div class="main-area">
    <?php 
    $pageTitle = 'Submit Liquidation';
    $pageSubtitle = 'Welcome, <strong>' . htmlspecialchars($_SESSION['officer_name']) . '</strong>';
    $basePath = '../';
    include $_SERVER['DOCUMENT_ROOT'] . '/bsit-fund-system-v2/includes/topbar.php'; 
    ?>

    <div class="page-content">
        <?php if ($message): ?><p class="success"><?= htmlspecialchars($message) ?></p><?php endif; ?>
        <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>

        <div class="card">
            <h3>Events Ready for Liquidation</h3>
            <?php if (count($events) == 0): ?>
                <p>No events waiting for liquidation.</p>
            <?php else: ?>
                <table>
                    <tr><th>Event</th><th>School Year</th><th>Budget</th><th>Action</th></tr>
                    <?php foreach ($events as $ev):
                        $evTotalBudget = $ev['allocated_amount'] ?? 0;
                        $evTotalExpenses = $ev['total_expenses'] ?? 0;
                        $evExpensesList = $eventExpenses[$ev['id']] ?? [];
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($ev['name']) ?></td>
                        <td><?= htmlspecialchars($ev['school_year']) ?></td>
                        <td><?= $evTotalBudget ? '₱' . number_format($evTotalBudget, 2) : '—' ?></td>
                        <td>
                            <button type="button" class="btn-submit" onclick='openLiquidationModal(
                                <?= $ev["id"] ?>,
                                <?= $ev["budget_id"] ?: "null" ?>,
                                <?= htmlspecialchars(json_encode($ev["name"]), ENT_QUOTES) ?>,
                                <?= $evTotalBudget ?>,
                                <?= htmlspecialchars(json_encode($evExpensesList), ENT_QUOTES) ?>
                            )'>
                                Submit Liquidation
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Liquidation Modal -->
<div id="liquidationModal" class="modal-overlay">
    <div class="modal-box">
        <button class="modal-close" onclick="closeLiquidationModal()">&times;</button>
        <h3>Submit Liquidation</h3>

        <form method="POST" enctype="multipart/form-data" id="liquidationForm"
              onsubmit="return openConfirmModal(this, 'Are you sure you want to submit this liquidation report?')">
            <input type="hidden" name="liquidation_submit" value="1">
            <input type="hidden" name="event_id" id="liqEventId">
            <input type="hidden" name="budget_id" id="liqBudgetId">

            <p style="font-size: 14px; margin-bottom: 16px;">
                <strong>Event:</strong> <span id="liqEventName"></span>
            </p>

            <div class="liq-preview-box">
                <h4><i class="fas fa-calculator"></i> Liquidation Summary</h4>
                <div class="liq-row">
                    <span>Total Budget:</span>
                    <strong id="liqBudgetDisplay">₱0.00</strong>
                </div>
                <div class="liq-row">
                    <span>Approved Expenses:</span>
                    <strong id="liqExpensesDisplay">₱0.00</strong>
                </div>
                <div class="liq-row">
                    <span>Pending Expenses:</span>
                    <strong id="liqPendingDisplay" style="color: #f39c12;">₱0.00</strong>
                </div>
                <div class="liq-row liq-total">
                    <span>Variance:</span>
                    <strong id="liqVarianceDisplay">₱0.00</strong>
                </div>
                <div id="liqWarning" class="liq-warning" style="display:none;">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span id="liqWarningText"></span>
                </div>
            </div>

            <div id="liqExpensesBox" class="liq-expenses-box" style="display:none;">
                <h4>
                    <span><i class="fas fa-list-ul"></i> Expenses Included</span>
                    <span class="count-badge" id="liqExpenseCount">0</span>
                </h4>
                <table class="liq-exp-table" id="liqExpensesTable">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Category</th>
                            <th>Payee</th>
                            <th style="text-align:right;">Amount</th>
                            <th style="text-align:center;">Status</th>
                        </tr>
                    </thead>
                    <tbody id="liqExpensesTbody"></tbody>
                </table>
                <div id="liqNoExpenses" style="display:none; text-align:center; padding: 15px 0; color:#999; font-size:12.5px;">
                    <i class="fas fa-inbox" style="font-size: 24px; color:#ddd; display:block; margin-bottom: 8px;"></i>
                    No expenses added yet. Click "Add Expense" below.
                </div>
                <button type="button" class="btn-add-expense" onclick="openQuickExpenseModal()">
                    <i class="fas fa-plus"></i> Add Expense
                </button>
            </div>

            <label id="liqNotesLabel">Notes</label>
            <textarea name="notes" id="liqNotes" rows="3" placeholder="Any additional notes for the auditor"></textarea>

            <label>Source Documents (receipts/attachments) <span style="color:red;">*</span></label>
            <input type="file" name="attachments[]" id="liqAttachments" multiple required accept="image/jpeg,image/png,image/gif,application/pdf">

            <button type="submit" class="btn-submit" style="width:100%; margin-top: 10px;"><i class="fas fa-paper-plane"></i> Submit Liquidation</button>
        </form>
    </div>
</div>

<!-- Nested Quick Add Expense Modal -->
<div id="quickExpenseModal" class="modal-overlay nested">
    <div class="modal-box nested-box">
        <button class="modal-close" onclick="closeQuickExpenseModal()">&times;</button>
        <h3><i class="fas fa-plus-circle"></i> Quick Add Expense</h3>
        <p style="font-size: 13px; color: #666; margin-bottom: 18px;">
            Event: <strong id="qeEventName"></strong>
        </p>

        <form id="quickExpenseForm" enctype="multipart/form-data">
            <input type="hidden" name="event_id" id="qeEventId">
            <input type="hidden" name="budget_id" id="qeBudgetId">
            <input type="hidden" name="school_year" id="qeSchoolYear">

            <label>Amount (₱):</label>
            <input type="number" step="0.01" name="amount" required>

            <div class="form-row">
                <div>
                    <label>Category:</label>
                    <select name="category_id" required>
                        <option value="">-- Select --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Fund Source:</label>
                    <select name="fund_source" required>
                        <option value="">-- Select --</option>
                        <?php foreach ($fundSources as $fs): ?>
                            <option value="<?= htmlspecialchars($fs) ?>"><?= htmlspecialchars($fs) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <label>Payee:</label>
            <select name="payee" id="qePayee" required>
                <option value="">-- Select Payee --</option>
                <?php foreach ($officers as $off): ?>
                    <option value="<?= htmlspecialchars($off['name']) ?>">
                        <?= htmlspecialchars($off['name']) ?> (<?= $off['position'] ?>)
                    </option>
                <?php endforeach; ?>
                <option value="__others__">Others (specify below)</option>
            </select>
            <div id="qePayeeOtherWrapper" style="display:none;">
                <input type="text" id="qePayeeOther" placeholder="Enter payee name">
            </div>

            <label>Date of Expense:</label>
            <input type="date" name="expense_date" value="<?= date('Y-m-d') ?>" required>

            <label>Description (optional):</label>
            <textarea name="description" rows="2"></textarea>

            <label>Source Documents <span style="color:red;">*</span></label>
            <input type="file" name="receipts[]" id="qeReceipts" multiple required accept="image/jpeg,image/png,image/gif,application/pdf">
            <p id="qeFileList" class="file-preview"></p>

            <div class="btn-row">
                <button type="button" class="btn-secondary" onclick="closeQuickExpenseModal()">Cancel</button>
                <button type="submit" class="btn-primary" id="qeSubmitBtn">
                    <i class="fas fa-save"></i> Save Expense
                </button>
            </div>
        </form>
    </div>
</div>

<div id="toastMsg" class="toast-success">
    <i class="fas fa-check-circle"></i>
    <span id="toastText">Expense added successfully</span>
</div>

<?php include '../../includes/shared_ui.php'; ?>

<script>
    let currentEventId = null;
    let currentBudgetId = null;
    let currentEventName = '';
    let currentTotalBudget = 0;
    let currentExpenses = [];

    function openLiquidationModal(eventId, budgetId, eventName, totalBudget, expensesList) {
        currentEventId = eventId;
        currentBudgetId = budgetId || null;
        currentEventName = eventName;
        currentTotalBudget = parseFloat(totalBudget) || 0;
        currentExpenses = expensesList || [];

        document.getElementById('liqEventId').value = eventId;
        document.getElementById('liqBudgetId').value = budgetId || '';
        document.getElementById('liqEventName').textContent = eventName;

        document.getElementById('liqNotes').value = '';
        document.getElementById('liqAttachments').value = '';

        renderLiquidationModal();
        document.getElementById('liquidationModal').classList.add('active');
    }

    function renderLiquidationModal() {
        const fmt = (n) => '₱' + n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');

        let approvedTotal = 0;
        let pendingTotal = 0;
        currentExpenses.forEach(exp => {
            if (exp.status === 'approved') {
                approvedTotal += parseFloat(exp.amount) || 0;
            } else {
                pendingTotal += parseFloat(exp.amount) || 0;
            }
        });

        const variance = currentTotalBudget - approvedTotal;

        document.getElementById('liqBudgetDisplay').textContent = fmt(currentTotalBudget);
        document.getElementById('liqExpensesDisplay').textContent = fmt(approvedTotal);
        document.getElementById('liqPendingDisplay').textContent = fmt(pendingTotal);

        const varianceEl = document.getElementById('liqVarianceDisplay');
        const warningEl = document.getElementById('liqWarning');
        const warningText = document.getElementById('liqWarningText');
        const notesField = document.getElementById('liqNotes');
        const notesLabel = document.getElementById('liqNotesLabel');

        let warningMessage = '';
        let warningType = '';
        let notesRequired = false;

        if (approvedTotal === 0 && pendingTotal === 0) {
            warningMessage = 'No expenses recorded for this event. Notes are required.';
            warningType = 'danger';
            notesRequired = true;
        } else if (variance < 0) {
            warningMessage = 'Over budget by ' + fmt(Math.abs(variance)) + '. Notes are required.';
            warningType = 'warn';
            notesRequired = true;
        } else if (pendingTotal > 0) {
            warningMessage = 'You have pending expenses worth ' + fmt(pendingTotal) + ' that are not yet approved. They will not count until approved.';
            warningType = 'warn';
        }

        if (variance >= 0) {
            varianceEl.textContent = fmt(variance) + ' Under';
            varianceEl.style.color = '#27ae60';
        } else {
            varianceEl.textContent = fmt(Math.abs(variance)) + ' Over';
            varianceEl.style.color = '#c0392b';
        }

        if (warningType) {
            warningEl.style.display = 'flex';
            warningText.textContent = warningMessage;
            warningEl.className = 'liq-warning' + (warningType === 'danger' ? ' danger' : '');
        } else {
            warningEl.style.display = 'none';
        }

        if (notesRequired) {
            notesField.required = true;
            notesField.placeholder = 'REQUIRED: Explain the situation for this liquidation...';
            notesLabel.innerHTML = 'Notes <span style="color:#f39c12;">*</span>';
        } else {
            notesField.required = false;
            notesField.placeholder = 'Any additional notes for the auditor';
            notesLabel.innerHTML = 'Notes';
        }

        const expBox = document.getElementById('liqExpensesBox');
        const expTbody = document.getElementById('liqExpensesTbody');
        const expCount = document.getElementById('liqExpenseCount');
        const noExpenses = document.getElementById('liqNoExpenses');
        const expTable = document.getElementById('liqExpensesTable');

        expBox.style.display = 'block';
        expTbody.innerHTML = '';

        if (currentExpenses.length > 0) {
            expTable.style.display = 'table';
            noExpenses.style.display = 'none';
            expCount.textContent = currentExpenses.length;

            currentExpenses.forEach(function(exp) {
                const statusMap = {
                    'approved': { cls: 'approved', label: 'Approved' },
                    'pending_audit': { cls: 'pending', label: 'Pending Audit' },
                    'audited': { cls: 'audited', label: 'Audited' }
                };
                const st = statusMap[exp.status] || { cls: 'pending', label: exp.status };

                const row = document.createElement('tr');
                row.innerHTML =
                    '<td>' + escapeHtml(exp.date) + '</td>' +
                    '<td>' + escapeHtml(exp.category) + '</td>' +
                    '<td>' + escapeHtml(exp.payee) + '</td>' +
                    '<td class="amount">' + fmt(parseFloat(exp.amount) || 0) + '</td>' +
                    '<td style="text-align:center;"><span class="status-pill ' + st.cls + '">' + st.label + '</span></td>';
                expTbody.appendChild(row);
            });
        } else {
            expTable.style.display = 'none';
            noExpenses.style.display = 'block';
            expCount.textContent = '0';
        }
    }

    function closeLiquidationModal() {
        document.getElementById('liquidationModal').classList.remove('active');
    }

    function openQuickExpenseModal() {
        document.getElementById('qeEventId').value = currentEventId;
        document.getElementById('qeBudgetId').value = currentBudgetId || '';
        document.getElementById('qeEventName').textContent = currentEventName;

        const form = document.getElementById('quickExpenseForm');
        form.reset();
        document.getElementById('qeEventId').value = currentEventId;
        document.getElementById('qeBudgetId').value = currentBudgetId || '';
        document.getElementById('qePayeeOtherWrapper').style.display = 'none';
        document.getElementById('qeFileList').textContent = '';

        const today = new Date().toISOString().split('T')[0];
        form.querySelector('[name="expense_date"]').value = today;

        document.getElementById('quickExpenseModal').classList.add('active');
    }

    function closeQuickExpenseModal() {
        document.getElementById('quickExpenseModal').classList.remove('active');
    }

    document.getElementById('qePayee').addEventListener('change', function() {
        const wrapper = document.getElementById('qePayeeOtherWrapper');
        const input = document.getElementById('qePayeeOther');
        if (this.value === '__others__') {
            wrapper.style.display = 'block';
            input.required = true;
            input.focus();
        } else {
            wrapper.style.display = 'none';
            input.required = false;
            input.value = '';
        }
    });

    document.getElementById('qeReceipts').addEventListener('change', function(e) {
        const list = document.getElementById('qeFileList');
        if (e.target.files.length > 0) {
            list.textContent = 'Selected: ' + Array.from(e.target.files).map(f => f.name).join(', ');
        } else {
            list.textContent = '';
        }
    });

    document.getElementById('quickExpenseForm').addEventListener('submit', async function(e) {
        e.preventDefault();

        const btn = document.getElementById('qeSubmitBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

        const payeeSelect = document.getElementById('qePayee');
        let payee = payeeSelect.value;
        if (payee === '__others__') {
            payee = document.getElementById('qePayeeOther').value.trim();
            if (!payee) {
                showToast('Please specify the payee name', true);
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save"></i> Save Expense';
                return;
            }
        }

        const formData = new FormData(this);
        formData.set('payee', payee);
        formData.delete('payee_select');

        try {
            const response = await fetch('../../ajax/quick_add_expense.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                currentExpenses.push({
                    id: data.expense.id,
                    date: data.expense.date,
                    category: data.expense.category,
                    payee: data.expense.payee,
                    amount: data.expense.amount,
                    status: data.expense.status
                });

                closeQuickExpenseModal();
                renderLiquidationModal();
                showToast('Expense added successfully!');

                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save"></i> Save Expense';
            } else {
                showToast(data.error || 'Failed to save expense', true);
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save"></i> Save Expense';
            }
        } catch (err) {
            showToast('Network error. Please try again.', true);
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-save"></i> Save Expense';
        }
    });

    function showToast(message, isError) {
        const toast = document.getElementById('toastMsg');
        const text = document.getElementById('toastText');
        text.textContent = message;
        toast.classList.toggle('toast-error', !!isError);
        toast.classList.add('show');
        setTimeout(() => toast.classList.remove('show'), 3000);
    }

    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    document.getElementById('liquidationModal').addEventListener('click', function(e) {
        if (e.target === this) closeLiquidationModal();
    });
    document.getElementById('quickExpenseModal').addEventListener('click', function(e) {
        if (e.target === this) closeQuickExpenseModal();
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (document.getElementById('quickExpenseModal').classList.contains('active')) {
                closeQuickExpenseModal();
            } else if (document.getElementById('liquidationModal').classList.contains('active')) {
                closeLiquidationModal();
            }
        }
    });

    document.getElementById('userMenu').addEventListener('click', function(e) {
        e.stopPropagation(); this.classList.toggle('show');
    });
    window.addEventListener('click', function() {
        document.getElementById('userMenu').classList.remove('show');
    });
</script>
</body>
</html>